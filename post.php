<?php 
$myText = $_POST['text'];
file_put_contents("log.txt", $myText, FILE_APPEND | LOCK_EX);
?>