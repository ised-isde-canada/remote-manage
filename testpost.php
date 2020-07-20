<?php

$data = "operation=s3list";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8080/manage.php');
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_HEADER, 0);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$result = curl_exec($ch);
curl_close($ch);

echo $result;
