<?

function getlogcontents($filename)
{
	$content = "";
	$line = "";	
	$flog = fopen($filename, "r");
	while(!feof($flog))
	{
		$line = fgets($flog, 256);
		if(!feof($flog))$content .= $line;//Remove the last line of the file in $content.
	}
	fclose($flog);
	return $content;
}

?>
