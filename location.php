<?php
$entity_type_manager = \Drupal::entityTypeManager();
$node_storage = $entity_type_manager->getStorage('node');

// Only get IDs for nodes that have lat/long but HAVENT been synced to the geofield yet
$query = $node_storage->getQuery()
  ->condition('type', 'tribe')
  ->exists('field_latitude')
  ->notExists('field_geoloc') // This prevents re-processing
  ->accessCheck(FALSE);

$nids = $query->execute();
echo "Found " . count($nids) . " nodes left to sync.\n";

foreach ($nids as $nid) {
  $node = $node_storage->load($nid);
  $lat = $node->field_latitude->value;
  $lon = $node->field_longitude->value;
  
  if ($lat && $lon) {
    $node->set('field_geoloc', "POINT ($lon $lat)");
    $node->save();
    echo "Synced: " . $node->getTitle() . "\n";
  }

  // CRITICAL: Free up memory
  $entity_type_manager->getStorage('node')->resetCache([$nid]);
}
echo "Sync complete!\n";