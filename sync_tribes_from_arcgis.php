<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

// 1. Boost Resources
// Processing 587 nodes with multiple taxonomy lookups requires significant memory.
ini_set('memory_limit', '1024M'); 
gc_enable();

// 2. Configuration
$url = "https://services1.arcgis.com/UxqqIfhng71wUT9x/arcgis/rest/services/TribalLeadership_Directory/FeatureServer/0/query?where=1%3D1&outFields=*&f=geojson";

echo "Connecting to ArcGIS FeatureServer...\n";

try {
  $response = \Drupal::httpClient()->get($url);
  $data = json_decode($response->getBody(), TRUE);
} catch (\Exception $e) {
  die("HTTP Error: " . $e->getMessage() . "\n");
}

if (!isset($data['features'])) {
  die("Error: No features found in GeoJSON response.\n");
}

echo "Starting Sync for " . count($data['features']) . " tribes...\n";

/**
 * Helper: Find or Create a Taxonomy Term ID.
 * This ensures the 'field_tribe' and others populate correctly.
 */
function get_term_id_by_name($name, $vocabulary_vid) {
  if (empty($name) || $name == 'N/A' || $name == 'None') return NULL;
  
  $name = trim($name);
  
  $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
    'name' => $name,
    'vid' => $vocabulary_vid,
  ]);
  
  $term = reset($terms);
  if (!$term) {
    $term = Term::create([
      'name' => $name, 
      'vid' => $vocabulary_vid
    ]);
    $term->save();
    echo "  [Taxonomy] Created '$name' in '$vocabulary_vid'\n";
  }
  return $term->id();
}

$count = 0;

foreach ($data['features'] as $feature) {
  $p = array_change_key_case($feature['properties'], CASE_LOWER);
  $arcgis_id = $p['objectid'] ?? null;

  // Find the node by the stable ArcGIS OBJECTID
  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'tribe')
    ->condition('field_arcgis_id', $arcgis_id)
    ->accessCheck(FALSE)
    ->execute();

  if ($nids) {
    $node = Node::load(reset($nids));

    // --- 1. TRIBE TAXONOMY MAPPING ---
    if (!empty($p['tribe'])) {
      $node->set('field_tribe', get_term_id_by_name($p['tribe'], 'tribe'));
    }

    // --- 2. STATE TAXONOMY MAPPINGS ---
    $node->set('field_physical_state', get_term_id_by_name($p['state'] ?? '', 'states'));
    $node->set('field_mailing_state',  get_term_id_by_name($p['mailingaddressstate'] ?? '', 'states'));

    // --- 3. REGIONAL TAXONOMY MAPPINGS ---
    if (!empty($p['biaregion'])) {
      $node->set('field_bia_region', get_term_id_by_name($p['biaregion'], 'bia_regions'));
    }

    if (!empty($p['biaagency'])) {
      $node->set('field_bia_agency', get_term_id_by_name($p['biaagency'], 'offices_bureaus'));
    }

    if (!empty($p['blmregion'])) {
      $node->set('field_bureau_of_land_management', get_term_id_by_name($p['blmregion'], 'bureau_of_land_management'));
    }

    if (!empty($p['fwsregion'])) {
      $node->set('field_fws_regions', get_term_id_by_name($p['fwsregion'], 'fws_regions'));
    }

    if (!empty($p['lartype'])) { 
      $node->set('field_lar_type', get_term_id_by_name($p['lartype'], 'lar_types'));
    }

    if (!empty($p['ancsaregion'])) {
      $node->set('field_ancsa_region', get_term_id_by_name($p['ancsaregion'], 'ancsa_region'));
    }

    if (!empty($p['npsregion'])) {
      $node->set('field_nps_unified_regions', get_term_id_by_name($p['npsregion'], 'nps_unified_regions'));
    }

    // --- 4. WEBSITE VALIDATION ---
    if (!empty($p['website']) && filter_var($p['website'], FILTER_VALIDATE_URL)) {
        $node->set('field_website', [
            'uri' => $p['website'],
            'title' => 'Website',
        ]);
    } else {
        $node->set('field_website', NULL);
    }

    // --- 5. TEXT & CONTACT FIELDS ---
    $node->set('field_organization', $p['organization'] ?? '');
    $node->set('field_aka', $p['aka'] ?? '');
    $node->set('field_notes', $p['notes'] ?? '');
    $node->set('field_first_name', $p['firstname'] ?? '');
    $node->set('field_last_name',  $p['lastname'] ?? '');
    $node->set('field_phone',      $p['phone'] ?? '');

    // --- 6. MULTI-VALUE EMAIL MAPPING ---
    if (!empty($p['email'])) {
        $raw_emails = str_replace(';', ',', $p['email']);
        $email_array = explode(',', $raw_emails);
        $cleaned_emails = [];
        foreach ($email_array as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $cleaned_emails[] = ['value' => $email];
            }
        }
        $node->set('field_email', $cleaned_emails);
    }

    // --- 7. ADDRESS TEXT FIELDS ---
    $node->set('field_physical_street', $p['physicaladdress'] ?? '');
    $node->set('field_physical_city',   $p['city'] ?? '');
    $node->set('field_physical_zip',    $p['zipcode'] ?? '');

    $node->set('field_mailing_street_po_box', $p['mailingaddress'] ?? '');
    $node->set('field_mailing_city',          $p['mailingaddresscity'] ?? '');
    $node->set('field_mailing_zip',           $p['mailingaddresszipcode'] ?? '');

    // --- 8. GEOLOCATION ---
    $lon = $p['longitude'] ?? null;
    $lat = $p['latitude']  ?? null;
    if ($lat && $lon && $lat != 0) {
      $node->set('field_geoloc', "POINT ($lon $lat)");
    }

    $node->save();
    
    // Memory Management: Clear static cache for the node
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
    $count++;

    if ($count % 50 == 0) {
      echo "> Processed $count tribes... Memory: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB\n";
      gc_collect_cycles(); 
    }
  }
}

echo "\nSUCCESS: $count tribes updated. Sync process complete.\n";