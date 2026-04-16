<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My First Chat</title>
</head>
<body>
	<?php 
		session_start();
		
		if(isset($_GET['logout'])){
			session_destroy();
			header("Location: index.php");
		}
		
		if(isset($_POST['enter'])){
			if($_POST['name'] != ""){
				$_SESSION['name'] = $_POST['name'];
			} else {
				echo '<span class="error">Please type in your name!</span>';
			}
		}
		
		function loginForm(){
			echo '
				<div id="loginform">
					<p>Please enter your name!</p>
					<form action="index.php" method="post">
						<label>Name:</label>
						<input type="text" name="name" id="name">
						<input type="submit" name="enter" id="enter" value="E N T E R">
					</form>
	</div>
			';
		}
	?>
	
	<?php 
		if(!isset($_SESSION['name'])){
			loginForm();
		} else {
	?>
	
	<div id="main">
		<div id="menu">
			<p>Welcome, <?php echo $_SESSION['name']; ?>!</p>
			<p class="logout"><a id="exit" href="#">E X I T</a></p>
		</div>
		<div id="chatbox">
			<?php echo $_POST['text']; ?>
		</div>
		<form name="message" action="">
			<input type="text" name="usermsg" id="usermsg">
			<input type="submit" name="submitmsg" id="submitmsg" value="S E N D">
		</form>
	</div>
	
	<?php 
	}
	?>
	<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	<script type="text/javascript" src="script.js">	
	</script>
</body>
</html>