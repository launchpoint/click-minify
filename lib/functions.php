<?

function minify($vpaths)
{
  global $run_mode;
  
  $info = pathinfo(HOME_FPATH . $vpaths[0]);
  
  $key = md5(join("|",$vpaths));
  $fname =  "$key.${info['extension']}";
  $minify_fname = "minify.$fname";
  $source_fpath = MINIFY_CACHE_FPATH . "/$fname";
  $minify_fpath = MINIFY_CACHE_FPATH . "/$minify_fname";

  $should_reminify = !file_exists($minify_fpath);
  if (!$should_reminify)
  {
    foreach($vpaths as $vpath)
    {
      $should_reminify |= is_newer(ROOT_FPATH.$vpath, $minify_fpath);
      if ($should_reminify)
      {
        break;
      }
    }
  }
  
  if(!$should_reminify) return MINIFY_CACHE_VPATH."/$minify_fname";

  $sources = array();
  foreach($vpaths as $vpath)
  {
    $src = file_get_contents(HOME_FPATH.$vpath);
    $enc = mb_detect_encoding($src);
    if($enc=='UTF-8') $src=str_replace("\xEF\xBB\xBF", '', $src);;
    if ($info['extension']=='css')
    {
      $pfx = dirname($vpath);
      $src = preg_replace_callback("/url\(\"(.+)?\"\)/", create_function( '$matches',
        "
          if (!startswith(\$matches[1], '/'))
          {
            \$path = '$pfx/'.\$matches[1];
            return 'url('.\$path.')';
          } else {
            \$path = \$matches[1];
          }
          return 'url('.\$path.')';
        
        "), $src);
      $src = preg_replace_callback("/url\('(.+)?'\)/", create_function( '$matches',
        "
          if (!startswith(\$matches[1], '/'))
          {
            \$path = '$pfx/'.\$matches[1];
            return 'url('.\$path.')';
          } else {
            \$path = \$matches[1];
          }
          return 'url('.\$path.')';
        
        "), $src);
      $src = preg_replace_callback("/url\((.+)?\)/", create_function( '$matches',
        "
          if (!startswith(\$matches[1], '/'))
          {
            \$path = '$pfx/'.\$matches[1];
            return 'url('.\$path.')';
          } else {
            \$path = \$matches[1];
          }
          return 'url('.\$path.')';
        
        "), $src);
    }

    $sources[] = "/* $vpath */";
    $sources[] = $src;
  }
  if($info['extension']=='css')
  {
    $source = join("\n",$sources);
  } else {
    $source = join(";\n",$sources);
  }
  $source = mb_convert_encoding($source,'UTF-8');
  
  file_put_contents($minify_fpath, $source);
  if ( !in($run_mode,RUN_MODE_DEVELOPMENT, RUN_MODE_TEST))
  {
    $jar = MINIFY_FPATH."/yuicompressor-2.4.2/build/yuicompressor-2.4.2.jar";
    $cmd = "java -jar $jar $minify_fpath -o $minify_fpath";
    $out='';
    $res = click_exec($cmd, 0, $out);
  }

  return MINIFY_CACHE_VPATH."/$minify_fname";
}


function minify_assets(&$event_data)
{
  global $run_mode;
  if($run_mode == RUN_MODE_DEVELOPMENT) return array();
  
  $assets = array();
  foreach($event_data as $event_name=>&$data)
  {
    if (array_key_exists('assets', $data))
    {
      $assets = array_merge($assets, $data['assets']);
      unset($data['assets']);
    }
  }

  $vpaths = array();
  foreach($assets as $asset)
  {
    $vpaths[] = $asset['src'];
  }
  $vpath = minify($vpaths);
  $assets = array(
    array('src'=>$vpath)
  );
  return $assets;
}