<?php
$query = \Drupal::entityQuery('node')->condition('type', 'tribe')->notExists('field_latitude')->accessCheck(FALSE);
$nids = $query->execute();
$nodes = \Drupal\node\Entity\Node::loadMultiple($nids);
$count = 0;

foreach ($nodes as $node) {
  $city = '';
  $state = '';
  foreach ($node->field_address as $item) {
    if ($item->entity) {
      $city = $item->entity->field_city->value;
      $state = $item->entity->field_state->value;
      if ($state) break;
    }
  }

  // Define our search attempts from most specific to least specific
  $attempts = [];
  
  // 1. Tribe Name + State
  if ($state) {
    $clean_name = preg_replace('/(of the|Cooperative|Association|Rancheria|Indians|Village).*/i', '', $node->getTitle());
    $attempts[] = trim($clean_name) . ", " . $state;
  }
  
  // 2. City + State (Cleaned)
  if ($city && $state) {
    $clean_city = preg_replace('/(Via|Box|#).*/i', '', $city);
    $attempts[] = trim($clean_city) . ", " . $state;
  }
  
  // 3. Just the City (If state is missing)
  if ($city && !$state) {
    $attempts[] = $city . ", USA";
  }

  $success = false;
  foreach ($attempts as $q) {
    $url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($q) . "&format=json&limit=1&countrycodes=us";
    $opts = ['http' => ['header' => "User-Agent: FederalGovProject/1.6\r\n"]];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);
    $data = json_decode($response);

    if (!empty($data)) {
      $node->set('field_latitude', $data[0]->lat);
      $node->set('field_longitude', $data[0]->lon);
      $node->save();
      echo "FIXED: " . $node->getTitle() . " (Match found for: $q)\n";
      $count++;
      $success = true;
      break; 
    }
    usleep(1100000); 
  }

  if (!$success) {
    echo "GIVING UP: " . $node->getTitle() . " - No geographic match found.\n";
  }
}
echo "Finished! Geocoded $count additional tribes.\n";