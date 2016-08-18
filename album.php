<html>
    <head><title>Photo-Album</title>
	<style>
		body {background-color: powderblue;}
		h1   {color: red;}
		b    {color: blue;}
</style>
	</head>
    <body>
		<fieldset><legend><H1>Upload-Photo</H1></legend>
		<form action="album.php" method="post" enctype="multipart/form-data">
		Choose a file<input type="file" name="uploadfile"/></br>
		<input type="submit" value="submit" name="submit"/>
		</form></fieldset>
		<fieldset>
		

<?php

// these 2 lines are just to enable error reporting and disable output buffering (don't include this in you application!)
error_reporting(E_ALL);
enable_implicit_flush();
// -- end of unneeded stuff

// if there are many files in your Dropbox it can take some time, so disable the max. execution time
set_time_limit(0);

require_once("DropboxClient.php");

// you have to create an app at https://www.dropbox.com/developers/apps and enter details below:
$dropbox = new DropboxClient(array(
	'app_key' => "",      // Put your Dropbox API key here
	'app_secret' => "",   // Put your Dropbox API secret here
	'app_full_access' => false,
),'en');


// first try to load existing access token
$access_token = load_token("access");
if(!empty($access_token)) {
	$dropbox->SetAccessToken($access_token);
}
elseif(!empty($_GET['auth_callback'])) // are we coming from dropbox's auth page?
{
	// then load our previosly created request token
	$request_token = load_token($_GET['oauth_token']);
	if(empty($request_token)) die('Request token not found!');
	
	// get & store access token, the request token is not needed anymore
	$access_token = $dropbox->GetAccessToken($request_token);	
	store_token($access_token, "access");
	delete_token($_GET['oauth_token']);
}

// checks if access token is required
if(!$dropbox->IsAuthorized())
{
	// redirect user to dropbox auth page
	$return_url = "http://".$_SERVER['HTTP_HOST'].$_SERVER['SCRIPT_NAME']."?auth_callback=1";
	$auth_url = $dropbox->BuildAuthorizeUrl($return_url);
	$request_token = $dropbox->GetRequestToken();
	store_token($request_token, $request_token['t']);
	die("Authentication required. <a href='$auth_url'>Click here.</a>");
}

function store_token($token, $name)
{
	if(!file_put_contents("tokens/$name.token", serialize($token)))
		die('<br />Could not store token! <b>Make sure that the directory `tokens` exists and is writable!</b>');
}

function load_token($name)
{
	if(!file_exists("tokens/$name.token")) return null;
	return @unserialize(@file_get_contents("tokens/$name.token"));
}

function delete_token($name)
{
	@unlink("tokens/$name.token");
}

function enable_implicit_flush()
{
	@apache_setenv('no-gzip', 1);
	@ini_set('zlib.output_compression', 0);
	@ini_set('implicit_flush', 1);
	for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
	ob_implicit_flush(1);
	echo "<!-- ".str_repeat(' ', 2000)." -->";
}
//upload code (choose an image from Uploads folder)
if(isset($_POST["submit"])){
	try{$imageFileType = pathinfo($_FILES["uploadfile"]["name"],PATHINFO_EXTENSION);

	if($imageFileType == "jpg" || $imageFileType == "jpeg" || $imageFileType == "JPG" || $imageFileType == "JPEG")
		{
			$imageName = $_FILES["uploadfile"]["name"];
			if(is_uploaded_file($_FILES["uploadfile"]["tmp_name"])){
				$imageData = file_get_contents($_FILES["uploadfile"]["tmp_name"]);
				move_uploaded_file($_FILES['uploadfile']['tmp_name'], 'uploads/'.$imageName);
				$dropbox->UploadFile('uploads/'.$imageName);
				echo "<b>Upload successful</b>";
		}
		
	}
	else
		{
			echo "<h1>.jpg/.jpeg files supported only</h1>";
		}
		}
	
	catch(Exception $e)
	{
		echo 'Message '. $e->getMessage();
	}
}
 

$files = $dropbox->GetFiles("",false);
echo '<div><fieldset><legend><H1>pictures in dropbox</H1></legend>';
		echo "</br><table align='center' border ='1'>
			<tr><th align='center'><h3>File Name</h3></th>
				<th align='center'><h3>Preview</h3></th>
				<th align='center'><h3>Delete</h3></th></tr>";
				foreach ($files as $file){
					echo "<tr><td align='center'><h4>".$file->path."</h4></td>
					<td align='center'><h4><a href='album.php?prev=".$file->path."'></h4>Preview</h4></a></td><td align='center'>
					<a href='album.php?delete=".$file->path."'>
					<h4><input type='submit' name='Delete' value='Delete'></h4>
					</a></input></td></tr>";
				}		 
				echo '</table></fieldset></div>';
				
echo '<div><fieldset><legend><H1>Preview</H1></legend>';				
	if(isset($_GET["prev"]) and trim($_GET["prev"])!=""){
		foreach ($files as $file){
			if($file->path==urldecode($_GET["prev"])){
				$test_file = "downloads/".basename($file->path);
				$dropbox->DownloadFile($file, $test_file);
				$img_data = base64_encode($dropbox->GetThumbnail($file->path,'l'));
				echo "<img src=\"data:image/jpeg;base64,$img_data\" alt=\"Generating PDF thumbnail failed!\" style=\"border: 1px solid black;\" />";
			}
		}
	}	
echo "</fieldset></div>";

if(isset($_GET["delete"]))
{
	try{
		foreach ($files as $file)
		{
			if($file->path==urldecode($_GET["delete"]))
			{
				$dropbox->Delete($file->path);
				echo "<meta http-equiv=\"refresh\" content=\"0;URL=album.php\">";
			}
		}
	}
	catch(Exception $e)
	{
		echo 'Message '. $e->getMessage();
	}
}

?>
	</body>
</html>