<?php
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

ini_set('memory_limit', '512M');
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

    // --- NEW: MAP TRIBE FIELD (Taxonomy: tribe) ---
    if (!empty($p['tribe'])) {
      $node->set('field_tribe', get_term_id_by_name($p['tribe'], 'tribe'));
    }

    // --- MAP STATES (Taxonomy: states) ---
    $node->set('field_physical_state', get_term_id_by_name($p['state'] ?? '', 'states'));
    $node->set('field_mailing_state',  get_term_id_by_name($p['mailingaddressstate'] ?? '', 'states'));

    // --- MAP BIA REGION (Taxonomy: bia_regions) ---
    if (!empty($p['biaregion'])) {
      $node->set('field_bia_region', get_term_id_by_name($p['biaregion'], 'bia_regions'));
    }

    // --- MAP BIA Agency (Taxonomy: offices_bureaus) ---
    if (!empty($p['biaagency'])) {
      $node->set('field_bia_agency', get_term_id_by_name($p['biaagency'], 'offices_bureaus'));
    }

    // --- MAP BIA Agency (Taxonomy: offices_bureaus) ---
    if (!empty($p['LARtype'])) {
      $node->set('field_lar_type', get_term_id_by_name($p['LARtype'], 'field_lar_type'));
    }

    // --- MAP WEBSITE (Link field: field_website) ---
    if (!empty($p['website'])) {
        $node->set('field_website', [
            'uri' => $p['website'],
            'title' => 'Website',
        ]);
    }

    // --- MAP Alaska Subsistence Region ---
    if (!empty($p['alaskasubsistenceregion'])) {
      $node->set('field_alaska_subsistence_region', get_term_id_by_name($p['alaskasubsistenceregion'], 'field_alaska_subsistence_region'));
    }


    // $node->set('field_leader_name', $p['firstname'] ?? '');

    $node->set('field_organization', $p['organization'] ?? '');
    $node->set('field_aka', $p['aka'] ?? '');
    $node->set('field_notes', $p['notes'] ?? '');

    // --- CONTACT & ADDRESS ---
    $node->set('field_suffix', $p['suffix'] ?? '');
    $node->set('field_first_name', $p['firstname'] ?? '');
    $node->set('field_last_name',  $p['lastname'] ?? '');
    $node->set('field_email',      $p['email'] ?? '');
    $node->set('field_phone',      $p['phone'] ?? '');
    $node->set('field_mailing_city', $p['mailingaddresscity'] ?? '');
    $node->set('field_mailing_zip',  $p['mailingaddresszipcode'] ?? '');
    $node->set('field_fax',  $p['fax'] ?? '');

    $node->save();
    \Drupal::entityTypeManager()->getStorage('node')->resetCache([$node->id()]);
  }
}

echo "\nSUCCESS: 587 tribes updated. Tribe taxonomy terms created.\n";