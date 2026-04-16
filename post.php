<?php 
session_start();
if(isset($_SESSION['name'])){
	$myText = $_POST['text'];
	$text_message = "<div class='msgln'><span class='chat-time'>".date("g:i A")."</span> <b class='user-name'>".$_SESSION['name']."</b> ".$myText."<br></div>";
	file_put_contents("log.html", $text_message, FILE_APPEND | LOCK_EX);
}
?>