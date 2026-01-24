<?php

use Drupal\node\Entity\Node;

$entity_type_manager = \Drupal::entityTypeManager();
$node_storage = $entity_type_manager->getStorage('node');

// Find all Tribe nodes that have the old paragraph field populated.
$nids = $node_storage->getQuery()
  ->condition('type', 'tribe')
  ->exists('field_address') 
  ->accessCheck(FALSE)
  ->execute();

echo "Found " . count($nids) . " nodes to process.\n";

foreach ($nids as $nid) {
  $node = $node_storage->load($nid);
  
  if (!$node->field_address->isEmpty()) {
    $paragraph = $node->field_address->entity;

    if ($paragraph) {
      // Corrected Mapping
      // node field <--- paragraph field
      $node->set('field_physical_street', $paragraph->field_street->value);
      $node->set('field_physical_city',   $paragraph->field_city->value);
      $node->set('field_physical_state',  $paragraph->field_state->value);
      
      // FIXED LINE: Use field_zip_code from Paragraph
      $node->set('field_physical_zip',    $paragraph->field_zip_code->value);
      
      $node->save();
      echo "Migrated: " . $node->getTitle() . " (Zip: " . $paragraph->field_zip_code->value . ")\n";
    }
  }
  $node_storage->resetCache([$nid]);
}

echo "All data moved to direct fields!\n";