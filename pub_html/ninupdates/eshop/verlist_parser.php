<!DOCTYPE html>
<!-- by mtheall -->
<html>
 <head>
  <style type='text/css'>
    table, td, th
    {
      border-style:    hidden;
      border-spacing:  0;
      border-collapse: collapse;
      vertical-align:  top;
      font-family:     "Courier New";
      font-size:       8pt;
      border-width: 1px 1px 1px 1px;
    }
  </style>
 </head>
 <body>
<?php
  include_once("/home/yellows8/ninupdates/config.php");

  $dir = "$sitecfg_workdir/versionlist/ctr/";

  function load_file($file)
  {
    global $dir;

    if(!preg_match('/^[0-9_-]+$/', $file))
      return null;

    $fp = fopen($dir . $file, 'rb');
    if(!$fp)
      return null;

    $data = array();

    $buf = fread($fp, 0x10);
    $buf = fread($fp, 0x10);
    while(strlen($buf) == 0x10)
    {
      $item = unpack('Ptid/Vvers/Vunk', $buf);
      $data[$item['tid']] = $item['vers'];
      $buf = fread($fp, 0x10);
    }

    ksort($data);
    return $data;
  }

  if(isset($_POST['from']) && isset($_POST['to']))
  {
    $from = load_file($_POST['from']);
    $to   = load_file($_POST['to']);

?>
  <a href=''>Back</a>
<?php
    if(!$from || !$to)
      printf("<p>Invalid version list</p>\n");
    else
    {
      $data = array();

      foreach($from as $key => $value)
        $data[$key] = array('from' => 'N/A', 'to' => 'N/A');
      foreach($to as $key => $value)
        $data[$key] = array('from' => 'N/A', 'to' => 'N/A');

      foreach($from as $key => $value)
        $data[$key]['from'] = (string)$value;
      foreach($to as $key => $value)
        $data[$key]['to'] = (string)$value;

      ksort($data);
?>
  <table>
   <tr>
    <th>TID</th>
    <th>From</th>
    <th>To</th>
   </tr>
<?php
      foreach($data as $key => $value)
      {
        if($value['from'] != $value['to'])
        {
          printf("   <tr>\n");
          printf("    <td>%016X</td>\n", $key);
          printf("    <td align='right'>%s</td>\n", $value['from']);
          printf("    <td align='right'>%s</td>\n", $value['to']);
          printf("   </tr>\n");
        }
      }
?>
  </table>
<?php
    }
  }
  else if(isset($_GET['date']))
  {
    $data = load_file($_GET['date']);
    if(!$data)
      printf("<p>Invalid version list</p>\n");
    else
    {
?>
  <table>
<?php
      foreach($data as $key => $value)
      {
        printf("   <tr><td>%016X</td><td align='right'>%lu</td></tr>\n",
               $key, $value);
      }
?>
  </table>
<?php
    }
  }
  else
  {
    $files = array();
    $dp = opendir($dir);
    if($dp)
    {
      while(false !== ($dent = readdir($dp)))
      {
        if(preg_match('/^[0-9_-]+$/', $dent))
          $files[date('Y-m-d H:i:s', filemtime($dir . $dent))] = $dent;
      }
    }

    krsort($files);
?>
  <form action='' method='POST'>
   <table id='table'>
    <tr>
     <th>From</th>
     <th>To</th>
     <th>Date</th>
    </tr>
<?php
    foreach($files as $file)
    {
      printf("    <tr>\n");
      printf("     <td><input type='radio' name='from' value='%s' /></td>\n", $file);
      printf("     <td><input type='radio' name='to'   value='%s' /></td>\n", $file);
      printf("     <td><a href='?date=%s'>%s</a></td>\n",
             urlencode($file), htmlentities($file));
      printf("    </tr>\n");
    }
?>
   </table>
   <div id='submit' style='position:fixed; top:10px;'>
    <input type='submit' value='Compare' />
   </div>
  </form>
  <script>
    function checked_index(el)
    {
      for(var i = 0; i < el.length; ++i)
      {
        if(el[i].checked)
          return i;
      }
    }

    function update_from()
    {
      var from = document.getElementsByName('from');
      var to   = document.getElementsByName('to');

      var from_checked = checked_index(from);
      var to_checked   = checked_index(to);

      for(var i = 0; i < from_checked; ++i)
        to[i].hidden = false;
      for(var i = from_checked; i < to.length; ++i)
        to[i].hidden = true;
    }

    function update_to()
    {
      var from = document.getElementsByName('from');
      var to   = document.getElementsByName('to');

      var from_checked = checked_index(from);
      var to_checked   = checked_index(to);

      for(var i = 0; i <= to_checked; ++i)
        from[i].hidden = true;
      for(var i = to_checked+1; i < to.length; ++i)
        from[i].hidden = false;
    }

    var el = document.getElementsByName('to');
    if(el && el.length > 1)
    {
      el[0].checked = true;
      for(var i = 0; i < el.length; ++i)
        el[i].onclick = update_to;
    }

    el = document.getElementsByName('from');
    if(el && el.length > 1)
    {
      el[0].hidden  = true;
      el[1].checked = true;
      for(var i = 0; i < el.length; ++i)
        el[i].onclick = update_from;
    }

    el = document.getElementById('submit');
    if(el)
    {
      var table  = document.getElementById('table');
      if(table)
        el.style.left = String(table.offsetWidth + 20) + "px";
    }

    update_from();
  </script>
<?php
  }
?>
 </body>
</html>

