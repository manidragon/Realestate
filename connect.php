<?php

$conn = new mysqli('localhost', 'root', '', 'realestate');

if ($conn) {

   
} else {
    die(mysqli_error($conn));
}