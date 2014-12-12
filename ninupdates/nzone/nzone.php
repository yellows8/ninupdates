<?php
//This is based on code by mtheall, which is based on the script from here: http://3dbrew.org/wiki/Talk:Nintendo_Zone#hotspot.conf_for_fw9

  function base64_fix($str)
  {
    $str = str_replace(".", "+", $str);
    $str = str_replace("-", "/", $str);
    $str = str_replace("*", "=", $str);
    return $str;
  }

  function parse_hotspotconf($filepath)
  {
	$linenum = 0;
	$f = file($filepath);

	echo "<html>\n <body>\n  <table border=\"1\">";
	foreach($f as $line)
	{
		switch($linenum++)
		{
			case 0:
			case 1:
			#echo $line;
			continue;

			case 2:
			$arr = explode(',', trim($line, "\n"));
			unset($arr[3]);
			unset($arr[5]);
			echo "   <tr>\n    <th>" . implode("</th>\n    <th>", $arr) . "</th>\n   </tr>\n";
			break;

			default:
			$arr = explode(',', trim($line, "\n"));
			$arr[0] = base64_decode(base64_fix($arr[0]));
			$arr[2] = base64_decode(base64_fix($arr[2]));
			unset($arr[3]);
			unset($arr[5]);
			echo "   <tr>\n    <td>" . implode("</td>\n    <td>", $arr) . "</td>\n   </tr>\n";
			break;
		}
	}
	echo "  </table>\n </body>\n</html>";
  }
?>
