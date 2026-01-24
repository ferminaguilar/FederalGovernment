<?php
use Drupal\node\Entity\Node;

$url = "https://services1.arcgis.com/UxqqIfhng71wUT9x/arcgis/rest/services/TribalLeadership_Directory/FeatureServer/0/query?where=1%3D1&outFields=*&f=geojson";
$response = \Drupal::httpClient()->get($url);
$data = json_decode($response->getBody(), TRUE);

foreach ($data['features'] as $feature) {
  $p = $feature['properties'];
  
  // ArcGIS lowercase keys
  $name = $p['tribename'] ?? null;
  
  if (!$name) continue;

  $nids = \Drupal::entityQuery('node')
    ->condition('type', 'tribe')
    ->condition('title', $name)
    ->accessCheck(FALSE)
    ->execute();

  if ($nids) {
    $node = Node::load(reset($nids));

    // Map Physical Address
    $node->set('field_physical_street', $p['physicaladdress'] ?? '');
    $node->set('field_physical_city',   $p['physicalcity']    ?? '');
    $node->set('field_physical_state',  $p['physicalstate']   ?? '');
    $node->set('field_physical_zip',    $p['physicalzipcode'] ?? '');

    // Map Mailing Address
    $node->set('field_mailing_street_po_box', $p['mailingaddress'] ?? '');
    $node->set('field_mailing_city',          $p['mailingcity']    ?? '');
    $node->set('field_mailing_state',         $p['mailingstate']   ?? '');
    $node->set('field_mailing_zip',           $p['mailingzipcode'] ?? '');

    // Map Coordinates (Directly from ArcGIS)
    $lon = $p['longitude'] ?? null;
    $lat = $p['latitude']  ?? null;
    
    if ($lat && $lon) {
        // Formats as WKT: POINT (longitude latitude)
        $node->set('field_geoloc', "POINT ($lon $lat)");
    }

    $node->save();
    echo "Updated: $name\n";
  } else {
    echo "Tribe not found in Drupal: $name\n";
  }
}