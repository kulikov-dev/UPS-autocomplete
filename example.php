<?php

use kulikov_dev\ups\address_processor;

// https://www.address.com/ups/?f=street&v=Mark&zip=21236&city=Baltimore&state=MD&type=1
$ups_connector = new address_processor(false);
$mysqli = new mysqli("localhost", "my_user", "my_password", "world");

$missing_params = [];
if (isset($_GET['zip'])) {
    $zip = mysqli_escape_string($mysqli, $_GET['zip']);
} else {
    $zip = '';
    array_push($missing_params, 'zip');
}

if (isset($_GET['state'])) {
    $state = mysqli_escape_string($mysqli, $_GET['state']);
} else {
    $state = '';
    array_push($missing_params, 'state');
}

if (isset($_GET['city'])) {
    $city = mysqli_escape_string($mysqli, $_GET['city']);
} else {
    $city = '';
    array_push($missing_params, 'city');
}

if (isset($_GET['street'])) {
    $street = mysqli_real_escape_string($mysqli, $_GET['street']);
} else {
    $street = '';
    array_push($missing_params, 'street');
}

if (isset($_GET['type'])) {
    $type = mysqli_escape_string($mysqli, $_GET['type']);      // 0 - PostCode, 1 - State, 2 - City, 3 - Address
} else {
    $type = 0;
    array_push($missing_params, 'type');
}

header('Content-Type: application/json; charset=utf-8');

if ($type < 0 or $type > 3) {
    $error = array("Error" => 1, "Message" => 'Param \'type\' is incorrect. Possible values are: 0 - zip, 1 - state, 2 - city, 3 - address');
    echo json_encode($error);
    die;
}

$result = [];
if (!empty($missing_params)) {
    $result['Warning'] = 1;
    $result['Messages'] = array('The following parameters are missing: ' . implode(", ", $missing_params) . '. The values were taken as empty.');
}

try {
    $output = $ups_connector->get_address_field_candidates('US', $zip, $state, $city, $street, $type);
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage();
    die;
}

if (!$output) {
    $result['Candidates'] = [];
    $result['Warning'] = 1;
    array_push($result['Messages'], 'No possible candidates didn\'t find with these parameters.');
} else {
    $result['Candidates'] = $output;
}

echo json_encode($result);
die;