<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

ini_set('memory_limit', '1024M'); // Bumped for the 500+ records
gc_enable();

$url = "https://services1.arcgis.com/UxqqIfhng71wUT9x/arcgis/rest/services/TribalLeadership_Directory/FeatureServer/0/query?where=1%3D1&outFields=*&f=geojson";
$response = \Drupal::httpClient()->get($url);
$data = json_decode($response->getBody(), TRUE);

echo "Starting Sync with Tribe Taxonomy mapping...\n";

/**
 * Helper: Find or Create a Taxonomy Term ID
 */
function get_term_id_by_name($name, $vocabulary_vid) {
  if (empty($name)) return NULL;
  $name = trim($name);
  
  $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    'name' => $name,
    'vid' => $vocabulary_vid,
  ]);
  
  $term = reset($terms);
  if (!$term) {
    $term = Term::create(['name' => $name, 'vid' => $vocabulary_vid]);
    $term->save();
    echo "  [Taxonomy] Created '$name' in '$vocabulary_vid'\n";
  }
  return $term->id();
}

$count = 0;

foreach ($data['features'] as $feature) {
  $p = array_change_key_case($feature['properties'], CASE_LOWER);
  $arcgis_id = $p['objectid'] ?? null;

  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'tribe')
    ->condition('field_arcgis_id', $arcgis_id)
    ->accessCheck(FALSE)
    ->execute();

  if ($nids) {
    $node = Node::load(reset($nids));

    // --- TAXONOMY MAPPINGS ---
    if (!empty($p['tribe'])) {
      $node->set('field_tribe', get_term_id_by_name($p['tribe'], 'tribe'));
    }

    $node->set('field_physical_state', get_term_id_by_name($p['state'] ?? '', 'states'));
    $node->set('field_mailing_state',  get_term_id_by_name($p['mailingaddressstate'] ?? '', 'states'));

    if (!empty($p['biaregion'])) {
      $node->set('field_bia_region', get_term_id_by_name($p['biaregion'], 'bia_regions'));
    }

    if (!empty($p['biaagency'])) {
      $node->set('field_bia_agency', get_term_id_by_name($p['biaagency'], 'offices_bureaus'));
    }

    // --- WEBSITE VALIDATION ---
    // ArcGIS data often has "N/A" or "None" which will crash a Drupal Link field
    if (!empty($p['website']) && filter_var($p['website'], FILTER_VALIDATE_URL)) {
        $node->set('field_website', [
            'uri' => $p['website'],
            'title' => 'Website',
        ]);
    }

    if (!empty($p['blmregion'])) {
      $node->set('field_bureau_of_land_management', get_term_id_by_name($p['blmregion'], 'field_bureau_of_land_management'));
    }

    if (!empty($p['fwsregion'])) {
      $node->set('field_fws_regions', get_term_id_by_name($p['fwsregion'], 'field_fws_regions'));
    }

    // --- TEXT FIELD MAPPINGS ---
    $node->set('field_organization', $p['organization'] ?? '');
    $node->set('field_aka', $p['aka'] ?? '');
    $node->set('field_notes', $p['notes'] ?? '');
    $node->set('field_first_name', $p['firstname'] ?? '');
    $node->set('field_last_name',  $p['lastname'] ?? '');
    // --- MULTI-VALUE EMAIL MAPPING ---
    if (!empty($p['email'])) {
        // Clean the string: replace semicolons with commas, then split by comma
        $raw_emails = str_replace(';', ',', $p['email']);
        $email_array = explode(',', $raw_emails);
        
        $cleaned_emails = [];
        foreach ($email_array as $email) {
            $email = trim($email);
            // Only add if it's a valid email format
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $cleaned_emails[] = ['value' => $email];
            }
        }

        if (!empty($cleaned_emails)) {
            $node->set('field_email', $cleaned_emails);
        }
    }
    $node->set('field_phone',      $p['phone'] ?? '');

    $node->save();
    
    // Memory Management
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
    $count++;

    if ($count % 50 == 0) {
      echo "Processed $count tribes...\n";
      gc_collect_cycles(); // Forces PHP to clean up memory
    }
  }
}

echo "\nSUCCESS: $count tribes updated. Sync complete.\n";