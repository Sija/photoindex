<?php

/* ___INFORMATIONS
  Author: Sijawusz Pur Rahnama
  e-Mail: sija@sija.info
  URI: http://sija.info/
  Version: 2.9

  This script is under GPL license.
*/

/* ___TODO
  * recursive przy allowed_dirs
  * ficzer: jezeli fotka jest wieksza niz podana szer [lub|i] szerokosc [wyswietlanie foty z maks. wart. i]
    podawanie linka do popupa z full sizem [?]
  * jezeli katalog podany jako ignored, albo allowed bedzie zaczynal sie znakiem '|', bedzie to traktowane
    jako sciezka wzgledna, w innym przypadku, bedzie sprawdzana tylko nazwa
  * xmle z opisami zdjec [+ tworzenie ?]
  * nie generowanie watermarkow jezeli obrazek jest mniejszy niz bok watermarka
  * integration with fotobuzz
*/

/* ___CONFIGURATION */

$conf['sitename'] = '__4_testing_purposes';
$conf['show_info'] = false;
$conf['fullpic_alone'] = true;
$conf['baloons'] = true;
$conf['alt_baloon'] = true; // used only if 'baloons' == true
$conf['separator'] = '/';
$conf['rewrite_path'] = 'gallery'; // remember about setting mod_rewrite
$conf['rewrite_suffix'] = false;
$conf['use_path_info'] = false;
$conf['recursive'] = true;
$conf['zlib_compress'] = 0; // it highly increase page generation

$conf['allowed_dirs'] = array();
$conf['ignored_dirs'] = array('cgi-bin', 'CVS', '.svn', 'includes', 'include'); // without trailing slash if it's relative path

$conf['watermarks'] = true;
$conf['watermark_file'] = 'sig.gif';
$conf['watermarks_dir'] = '.watermarks'; // without any slashes!
$conf['watermarks_position'] = 'br'; // y [t|m|b] + x [l|m|r]
$conf['watermarks_cache'] = true; // remember about chmoding dirs
$conf['watermarks_compression'] = 100;

$conf['thumbs_dir'] = '.thumbs'; // without any slashes!
$conf['thumbs_cache'] = true; // remember about chmoding dirs
$conf['thumbs_in_line'] = 10;
$conf['thumbs_crop'] = true; // used only if 'thumbs_square' == true
$conf['thumbs_square'] = true;
$conf['thumbs_compression'] = 50;
$conf['thumbs_side'] = 60; // if 'thumbs_square' == false, this will be the smaller side

/* ___CORE */

set_time_limit(0);
ignore_user_abort(true);
set_magic_quotes_runtime(0);

ini_set('arg_separator.output', '&amp;');
ini_set('zlib.output_compression', $conf['zlib_compress']);
ini_set('zlib.output_compression_level', 9);

$version = '2.9';

if(!$conf['zlib_compress']) ob_start('ob_gzhandler');

session_name('sid');
session_start();

$timeparts = explode(' ', microtime());
$starttime = $timeparts[1].substr($timeparts[0], 1);

if($_rp = $conf['rewrite_path']) {
  $_ap = explode('/', $_SERVER['SCRIPT_NAME']);
  array_pop($_ap);
  if($_rp{0} == '/') $_rp = substr($_rp, 1);
  if(substr($_rp, -1) != '/') $_rp .= '/';
  $_s = implode('/', $_ap).'/'.$_rp;
} else {
  if($conf['use_path_info']) {
    $_s = $_SERVER['SCRIPT_NAME'].'/';
  } else {
    $_s = preg_replace('/index\.(php[345]?|[p]?htm[l]?)$/', null, $_SERVER['SCRIPT_NAME']).'?';
  }
}

$photos = $dirs = array();
$__ = (($conf['separator']) ? $conf['separator'] : '/');
$_i = explode($__, (($conf['use_path_info']) ? substr($_SERVER['PATH_INFO'], 1) : $_SERVER['QUERY_STRING']));
$_ad = (dirname($_SERVER['SCRIPT_NAME']) == '/') ? null : dirname($_SERVER['SCRIPT_NAME']);

if(!empty($conf['rewrite_suffix']) && $_t = count($_i)) {
  $_rs = $conf['rewrite_suffix'];
  $_l =& $_i[($_t-1)];

  if(substr($_l, -(strlen($_rs))) == $_rs) {
    $_l = substr($_l, 0, -(strlen($_rs)));
  }
}

$conf['ignored_dirs'][] = '..';
$conf['ignored_dirs'][] = $conf['thumbs_dir'];
$conf['ignored_dirs'][] = $conf['watermarks_dir'];

if(!isset($_SESSION['cache_time']) || ($_SESSION['cache_time'] < filemtime(__FILE__))) {
  exploreDir();
  foreach($dirs AS $key => $val) {
    if(!$photos[$key][0]) unset($dirs[$key]);
  }
  $_SESSION = array(
    'cache_time' => filemtime(__FILE__),
    'cache_photos' => $photos,
    'cache_dirs' => $dirs
  );
} else {
  $photos = $_SESSION['cache_photos'];
  $dirs = $_SESSION['cache_dirs'];
}

#print_r($dirs); print_r($photos); exit;

$_p = (isset($_i[1]) && $dirs[$_i[1]]) ? $_i[1] : @min(array_flip($dirs));
$_j = (isset($_i[2]) && $photos[$_p][$_i[2]]) ? $_i[2] : ($conf['fullpic_alone'] ? -1 : 0);
$_o = $dirs[$_p]; $_f = $photos[$_p][$_j];
$_n = count($photos[$_p]);

if($_i[0] == 'random') {
  if(empty($_i[1])) {
    foreach($dirs AS $key => $val) $rd[] = $key;
    $_p = $rd[mt_rand(0, count($rd)-1)];
  }

  $rand = mt_rand(0, count($photos[$_p])-1);
  header('Location: '.genURI('show', $_p, $rand));
  exit;
} elseif($_i[0] == 'regenerate') {
  cleanDaShit();
  header('Location: '.genURI());
  exit;
} elseif($_i[0] == 'recache') {
  $_SESSION = array();
  touch(__FILE__);

  header('Location: '.genURI());
  exit;
}

/* ___MISC_FUNCTIONS */

function genURI() {
  global $conf, $_s, $__;
  static $s2 = false;

  if(empty($s2)) $s2 = ((!$conf['rewrite_path'] && !$conf['use_path_info']) ? substr($_s, 0, -1) : $_s);
  $vars = func_get_args();
  return((count($vars) ? $_s.implode($__, $vars).$conf['rewrite_suffix'] : $s2));
}

function indexOf($array, $value) {
  foreach($array AS $k => $v) {
    if($v == $value) return($k);
  }
  return(false);
}

function rmDirr($obj) {
  if(!file_exists($obj)) return(false);
  if(is_file($obj) || is_link($obj)) return(unlink($obj));

  $dir = dir($obj);
  while(false !== $entry = $dir->read()) {
    if($entry != '.' && $entry != '..') {
      rmDirr($obj.'/'.$entry);
    }
  }
  $dir->close();
  return(rmDir($obj));
}

function cleanDaShit() {
  global $conf, $dirs;

  foreach($dirs AS $dir) {
    if(is_dir($dir.'/'.$conf['thumbs_dir'])) rmDirr($dir.'/'.$conf['thumbs_dir']);
    if(is_dir($dir.'/'.$conf['watermarks_dir'])) rmDirr($dir.'/'.$conf['watermarks_dir']);
  }
}

function unhtmlentities($string) {
  $trans_tbl = get_html_translation_table(HTML_ENTITIES);
  $trans_tbl = array_flip($trans_tbl);
  $ret = strtr($string, $trans_tbl);

  return(preg_replace('/&#(\d+);/me' , "chr('\\1')", $ret));
}

function escapeStr($str, $_u = true) {
  return(strtolower(htmlentities((($_u) ? ereg_replace('_', ' ', implode(' > ', explode('/', (($str == '.') ? '[root]' : $str)))) : $str))));
}

function rptStr($str, $_c) {
  for($i = 0; $i <= $_c; $i++) $_s .= $str;
  return($_s);
}

function cutStr($str, $size, $suffix = '...') {
  return(((strlen($str) <= $size) ? $str : substr($str, 0, $size-strlen($suffix)).$suffix));
}

function ext2imgFunc($file) {
  preg_match('/\.([^\.]+)$/i', $file, $_m);
  switch(strtolower($_m[1])) {
    case 'jpg':
    case 'jpeg':
      if (imageTypes() & IMG_JPG) $ext = array('jpeg', 'jpeg');
      break;
    case 'gif':
      if (imageTypes() & IMG_GIF) $ext = array('gif', 'gif');
      elseif (imageTypes() & IMG_PNG) $ext = array('gif', 'png');
      break;
    case 'png':
      if (imageTypes() & IMG_PNG) $ext = array('png', 'png');
      break;
    default:
      return(false);
      break;
  }
  return((!empty($ext)) ? array('ImageCreateFrom'.$ext[0], 'image'.$ext[1]) : false);
}

function file264($file) {
  ob_start();
  readfile($file);
  return(base64_encode(ob_get_clean()));
}

/* ___DIRECTORY_EXPLORING */

function exploreDir($__d = '.', $depth = 0) {
  global $conf, $dirs, $photos, $_ad;
  static $i = 0;

  $__r = (!$depth && (in_array('|.', $conf['allowed_dirs']) || !in_array('|.', $conf['ignored_dirs']))) ? 1 : 0;

  if($handle = dir($__d)) {
    $__d .= '/';
    while(false !== $obj = $handle->read()) {
      #echo("i:<b>$i</b> | r:<b>$__r</b> | d:<b>$__d</b> | o:<b>$obj</b><br/>\n");
      if(is_dir($__d.$obj)) {
        $is_ignored = in_array($obj, $conf['ignored_dirs']) || in_array('|'.substr($__d.$obj, 2), $conf['ignored_dirs']);
        $is_allowed = in_array($obj, $conf['allowed_dirs']) || in_array('|'.substr($__d.$obj, 2), $conf['allowed_dirs']);
        if(((count($conf['allowed_dirs']) && $is_allowed) ||
        (!count($conf['allowed_dirs']) && !$is_ignored)) && (!preg_match('/([\.]{1,2})$/i', $obj) || $__r)) {
          $dirs[$i] = substr($__d.$obj, 2);
          if(is_dir($__d.$obj.'/'.$conf['thumbs_dir']) && !$conf['thumbs_cache']) {
            rmDirr($__d.$obj.'/'.$conf['thumbs_dir']);
          } 
          if(is_dir($__d.$obj.'/'.$conf['watermarks_dir']) && (!$conf['watermarks'] || !$conf['watermarks_cache'])) {
            rmDirr($__d.$obj.'/'.$conf['watermarks_dir']);
          }
          $i++;
          ($conf['recursive'] && (!$__r || $__r && $obj != '.')) ? exploreDir($__d.$obj, $depth+1) : false;
        }
        unset($match);
      } elseif(is_file($__d.$obj)) {
        if(preg_match('/\.(jpg|jpeg|png|gif)$/i', $obj) && (substr($__d, 2) || $__r)) {
          if(dirname($conf['watermark_file']) == rtrim($__d, '/') && $conf['watermark_file'] == $obj) continue;

          $i = ($__r) ? 1 : $i;
          $_d = ($v = indexOf($dirs, substr($__d, 2, strlen($__d)-3))) ? $v : 0;
          $_a = getImageSize($__d.$obj);
          $p = str_replace('%2F', '/', rawurlencode($_ad.'/'.substr($__d, 2)));
          $_ts = $conf['thumbs_side'];

          if(!file_exists($__d.$conf['thumbs_dir'].'/'.$obj) && $conf['thumbs_cache']) {
            if(($_a[0] < $_ts) && ($_a[1] < $_ts)) {
              $icon = true;
            } else {
              $_tc = true;
              if(!is_dir($__d.$conf['thumbs_dir'])) $_tc = @mkdir($__d.$conf['thumbs_dir'], 0777);
              $_e = ($_tc) ? ((makeThumb($obj, $dirs[($_d)])) ? false : true) : true;
            }
          } elseif(file_exists($__d.$conf['thumbs_dir'].'/'.$obj) && $conf['thumbs_cache']) {
            $size = getImageSize($__d.$conf['thumbs_dir'].'/'.$obj);
            $smaller = ($size[0] <= $size[1]) ? $size[0] : $size[1];

            if(($smaller != $_ts) || (($size[0] != $size[1]) && $conf['thumbs_square'])) {
              unlink($__d.$conf['thumbs_dir'].'/'.$obj);
              $_e = (makeThumb($obj, $dirs[($_d)])) ? false : true;
            }
          }

          if($conf['watermarks'] && $conf['watermarks_cache'] && !file_exists($__d.$conf['watermarks_dir'].'/'.$obj)) {
            $_tw = true;
            if(!is_dir($__d.$conf['watermarks_dir'])) $_tw = @mkdir($__d.$conf['watermarks_dir'], 0777);
            $_we = ($_tw) ? ((watermark($obj, $dirs[$_d])) ? false : true) : true;
          }

          $photos[$_d][] = array(
            'file' => htmlentities($obj),
            'w' => $_a[0],
            'h' => $_a[1],
            'time' => date('Y.m.d <b>@</b> H:i', filemtime($__d.$obj)),
            'size' => round(filesize($__d.$obj) / 1024, 1),
            'thumb' => ((isset($icon)) ? $p.rawurlencode($obj) : ((!$conf['thumbs_cache'] || $_e) ? genURI('thumb', $_d, count($photos[$_d])) : $p.$conf['thumbs_dir'].'/'.rawurlencode($obj))),
            'watermark' => ((!$conf['watermarks_cache'] || $_we) ? genURI('watermark', $_d, count($photos[$_d])) : $p.$conf['watermarks_dir'].'/'.rawurlencode($obj))
          );
        }
      }
    }
  }
  $handle->close();
}

/* ___IMAGE_TRANSFORMATION */

function transformImg($file, $dir, $mode) {
  $f = ext2imgFunc($file);
  if (!$f) return(false);

  $source = $f[0]($dir.'/'.$file);
  $width = imageSX($source);
  $height = imageSY($source);

  $target = imageCreateTrueColor($width, $height);

  for($cnt1 = 0; $cnt1 < $width; $cnt1++) {
    for($cnt2 = 0; $cnt2 < $height; $cnt2++) {
      $rgb = imageColorat($source, $cnt1, $cnt2);

      $r = ($rgb  >> 16) & 0xFF;
      $g = ($rgb  >> 8) & 0xFF;
      $b = $rgb  & 0xFF;

      $gray = (0.30 * $r) + (0.59 * $g) + (0.11 * $b);

      switch(strtolower($mode)) {
        case 'sepia':
          $color = imageColorAllocate($target, $gray, (0.89 * $gray), (0.74 * $gray));
          break;
        case 'bw':
          $color = imageColorAllocate($target, $gray, $gray, $gray);
          break;
        case 'invert':
          $color = imageColorAllocate($target, (255 - $r), (255 - $g), (255 - $b));
          break;
        default:
          return(false);
          break;
       }
      imageSetPixel($target, $cnt1, $cnt2, $color);
    }
  }
  if (!@$f[1]($target)) return(false);

  ImageDestroy($source);
  return(true);
}

/* ___THUMBNAILS_GENERATION */

function makeThumb($file, $dir, $write = true) {
  global $conf;

  $f = ext2imgFunc($file);
  if (!$f) return(false);

  $side = $conf['thumbs_side'];
  $in_img = $f[0]($dir.'/'.$file);
  $in_w = ImageSX($in_img);
  $in_h = ImageSY($in_img);

  if($conf['thumbs_square']) {
    $out_h = $out_w = $side;
    $out_img = ImageCreateTrueColor($out_w, $out_h);

    if($conf['thumbs_crop']) {
      if($in_w > $in_h) {
        $in_w = $in_h;
      } elseif($in_w < $in_h) {
        $in_h = $in_w;
      }
      imageCopyResampled($out_img, $in_img, 0, 0, 0, 0, $out_w, $out_h, $in_w, $in_h);
    } else {
      if($in_w > $in_h) {
        $ratio = $in_h / $in_w;
        $new_shortside = $side * $ratio;
        $new_y = (($side - $new_shortside) / 2);
        $in_h = $in_w;
        imageCopyResampled($out_img, $in_img, 0, $new_y, 0, 0, $out_w, $out_h, $in_w, $in_h);
      } elseif($in_w < $in_h) {
        $ratio = $in_w / $in_h;
        $new_shortside = $side * $ratio;
        $new_x = (($side - $new_shortside) / 2);
        $in_w = $in_h;
        imageCopyResampled($out_img, $in_img, $new_x, 0, 0, 0, $out_w, $out_h, $in_w, $in_h);
      }
    }
  } elseif($in_w > $in_h) {
    $out_h = $side;
    $out_w = ($in_w / $in_h) * $side;
    $out_img = ImageCreateTrueColor($out_w, $out_h);
    imageCopyResampled($out_img, $in_img, 0, 0, 0, 0, $out_w, $out_h, $in_w, $in_h);
  } elseif($in_w == $in_h) {
    $out_h = $out_w = $side;
    $out_img = ImageCreateTrueColor($out_w, $out_h);
    imageCopyResampled($out_img, $in_img, 0, 0, 0, 0, $out_w, $out_h, $in_w, $in_h);
  } elseif($in_w < $in_h) {
    $out_w = $side;
    $out_h = ($in_h / $in_w) * $side;
    $out_img = ImageCreateTrueColor($out_w, $out_h);
    imageCopyResampled($out_img, $in_img, 0, 0, 0, 0, $out_w, $out_h, $in_w, $in_h);
  }
  ImageAlphaBlending($out_img, true);

  if (!@$f[1]($out_img, (($write) ? $dir.'/'.$conf['thumbs_dir'].'/'.$file : ''), $conf['thumbs_compression'])) return(false);

  ImageDestroy($in_img);
  ImageDestroy($out_img);
  return(true);
}

/* ___WATERMARK_GENERATION */

function watermark($file, $dir, $write = true) {
  global $conf;

  $f = ext2imgFunc($file); if(!$f) return(false);
  $fl = ext2imgFunc($conf['watermark_file']); if(!$fl) return(false);

  $photoImage = $f[0]($dir.'/'.$file);
  $logoImage = $fl[0]($conf['watermark_file']);

  $imgW = ImageSX($photoImage);
  $imgH = ImageSY($photoImage);
  $logoW = ImageSX($logoImage);
  $logoH = ImageSY($logoImage);

  $horizExtra = $imgW - $logoW;
  $vertExtra = $imgH - $logoH;

  ImageAlphaBlending($photoImage, true);

  switch($conf['watermarks_position']{0}) {
    case 't':
      $vertMargin = 0;
      break;
    case 'm':
      $vertMargin = round($vertExtra / 2);
      break;
    default: // 'b'
      $vertMargin = $vertExtra;
      break;
  }

  switch($conf['watermarks_position']{1}) {
    case 'l':
      $horizMargin = 0;
      break;
    case 'm':
      $horizMargin = round($horizExtra / 2);
      break;
    default: // 'r'
      $horizMargin = $horizExtra;
      break;
  }

  ImageCopy($photoImage, $logoImage, $horizMargin, $vertMargin, 0, 0, $logoW, $logoH);
  if (!@$f[1]($photoImage, (($write) ? $dir.'/'.$conf['watermarks_dir'].'/'.$file : ''), $conf['watermarks_compression'])) return(false);

  ImageDestroy($photoImage);
  ImageDestroy($logoImage);
  return(true);
}

/* ___CSS */

if($_i[0] == 'css') {
  header('Content-Type: text/css');
  header('Cache-Control: public, must-revalidate', false);
?>
body {
  margin: 0px;
  background-color: #fff;
  cursor: default;
}

body, td {
  color: #000;
  font-family: Tahoma, Arial, Helvetica, sans-serif;
  font-size: 11px;
}

acronym, abbr {
  cursor: help;
  border-bottom: 1px dotted #000;
}

a {
  color: #666;
  font-family: Tahoma, Arial, Helvetica, sans-serif;
  text-decoration: none;
}

a:hover {
  color: #fff;
  text-decoration: none;
  font-family: Tahoma, Arial, Helvetica, sans-serif;
  background-color: #d20000;
}

#header {
  width: 100%;
  height: 88px;
  background-image: url(<?php echo(genURI('img', 'bg')); ?>);
  border-bottom: 1px solid #d20000;
}

#header #title {
  font-family: 'Trebuchet MS', Tahoma, Arial, Helvetica, sans-serif;
  font-size: 27px;
  vertical-align: baseline;
  font-weight: bold;
  padding: 20px;
  background-color: #eeeeee;
  border-bottom: 3px solid #fff;
  border-right: 3px solid #fff;
  float: left;
  width: 600px;
  white-space: nowrap;
  opacity: .60;
  filter: alpha(opacity=60);
  -moz-opacity: .60;
  -moz-border-radius-bottomright: 75%;
}

#photos img {
  border: 1px solid #999;
  background-color: transparent;
}

#dirs {
  width: 98%;
  padding: 10px;
  border-bottom: 1px solid #eee;
}

#footer {
  padding-top: 10px;
  border-top: 1px solid #eee;
}

#footer span {
  color: #d20000;
  font-weight: bold;
}

#main {
  padding: 10px;
}

#info {
  padding-top: 5px;
  text-align: justify;
}

#back {
  color: #ffcc00;
  vertical-align: middle;
}

.nonalpha {
  color: #999;
}

.pixel {
  vertical-align: top;
  border: 0 !important;
}  

#random, .extras, .extras:hover {
  vertical-align: super;
  font-size: 10px;
  font-weight: bold;
  font-family: Verdana, Tahoma, Helvetica, sans-serif;
  color: #d20000;
  background-color: transparent;
  padding-left: 1px;
}

#album {
  font-family: 'Trebuchet MS', Tahoma, Helvetica, sans-serif;
  font-size: 17px;
  padding-top: 30px;
}

.baloonbg {
  background-color: #dcdbd3;
  -moz-border-radius-bottomleft: 10px;
  -moz-border-radius-bottomright: 10px;
  -moz-border-radius-topleft: 0;
  -moz-border-radius-topright: 10px;
  -moz-opacity: .85;
  opacity: .85;
  filter: alpha(opacity=85);
  border: 1px solid #6a6c6b;
  position: absolute;
  display: none;
  z-index: 2;
}

#baloon {
  position: absolute;
  display: none;
  z-index: 3;
}

#baloonThumb {
  position: absolute;
  display: none;
  z-index: 50;
}
<?php
  exit;

/* ___THUMBNAIL_ON_FLY */

} elseif ($_i[0] == 'thumb' && $dirs[$_i[1]] && $photos[$_i[1]][$_i[2]]) {
  $file = unhtmlentities($photos[$_p][$_i[2]]['file']);
  preg_match('/\.([^\.]+)$/i', $file, $_m);

  header('Content-Type: image/'.$_m[1]);
  header('Content-Disposition: filename=thumb-'.$file);
  header('Cache-Control: public, must-revalidate', false);

  if(!makeThumb($file, $dirs[$_i[1]], false)) echo('makeThumb(): probably this extension ['.$_m[1].'] is unsupported by your GD library.');
  exit;

/* ___WATERMARK_ON_FLY */

} elseif ($_i[0] == 'watermark' && $dirs[$_i[1]] && $photos[$_i[1]][$_i[2]]) {
  $file = unhtmlentities($photos[$_p][$_i[2]]['file']);
  preg_match('/\.([^\.]+)$/i', $file, $_m);

  header('Content-Type: image/'.$_m[1]);
  header('Content-Disposition: filename='.$file);
  header('Cache-Control: public, must-revalidate', false);

  if(!watermark($file, $dirs[$_i[1]], false)) echo('watermark(): probably this extension ['.$_m[1].'] is unsupported by your GD library.');
  exit;

/* ___TRANSFORM_IMAGES_ON_FLY */

} elseif ($_i[0] == 'transform' && $dirs[$_i[1]] && $photos[$_i[1]][$_i[2]]) {
  switch(strtolower($_i[3])) {
    case 'sepia':
    case 'bw':
    case 'invert':
      $file = unhtmlentities($photos[$_p][$_i[2]]['file']);
      preg_match('/\.([^\.]+)$/i', $file, $_m);

      header('Content-Type: image/'.$_m[1]);
      header('Content-Disposition: filename='.$_i[3].'-'.$file);
      header('Cache-Control: public, must-revalidate', false);

      $f = $dirs[$_i[1]].'/'.$conf['watermarks_dir'].'/'.$file;
      if($conf['watermarks'] && file_exists($f)) {
        $dirs[$_i[1]] .= '/'.$conf['watermarks_dir'];
      } elseif($conf['watermarks'] && !file_exists($f)) {
        header('Location: '.genURI('watermark', $_i[1], $_i[2]));
        exit;
      }

      if(!transformimg($file, $dirs[$_i[1]], $_i[3])) echo('transformimg(): probably this extension ['.$_m[1].'] is unsupported by your GD library.');
      break;
    default:
      header('Location: '.genURI());
      break;
  }
  exit;

/* ___JS */

} elseif ($_i[0] == 'js') {
  header('Content-Type: text/javascript');
  header('Cache-Control: public, must-revalidate', false);
  echo('//<![CDATA['."\n");

  switch($_i[1]) {
    case 'baloon':
?>
function showBaloon(elemID, txt, size) {
  var offsetTrail = document.getElementById(elemID);
  var offsetLeft = 0;
  var offsetTop = 0;

  while(offsetTrail) {
    offsetLeft += offsetTrail.offsetLeft;
    offsetTop += offsetTrail.offsetTop;
    offsetTrail = offsetTrail.offsetParent;
  }

  if(navigator.userAgent.indexOf('Mac') != -1 && typeof document.body.leftMargin != 'undefined') {
    offsetLeft += document.body.leftMargin;
    offsetTop += document.body.topMargin;
  }

  img = document.getElementById(elemID);

  document.getElementById('baloonbg').style.display = 'block';
  document.getElementById('baloonbg').style.top = eval(offsetTop-12) + 'px';
  document.getElementById('baloonbg').style.left = eval(offsetLeft-15) + 'px';
  document.getElementById('baloonbg').style.width = eval(size-(-<?php echo($conf['alt_baloon'] ? '170' : '175'); ?>)) + 'px';
  document.getElementById('baloonbg').style.height = eval(size-(-<?php echo($conf['alt_baloon'] ? '26' : '30'); ?>)) + 'px';
  document.getElementById('baloonbg').onmouseout = 'hideBaloon();';

  document.getElementById('baloon').style.display = 'block';
  document.getElementById('baloon').style.top = eval(offsetTop-10) + 'px';
  document.getElementById('baloon').style.left = eval(offsetLeft-(-size-5)) + 'px';
  document.getElementById('baloon').style.width = '148px';
  document.getElementById('baloon').style.height = eval(size-(-18)) + 'px';
  document.getElementById('baloon').innerHTML = '<div style="text-align: right; padding: 8px 7px 0 5px" onmouseout="hideBaloon();"><strong>' + img.title + '</strong>' + ((size >= 60) ? '<span style="font-family: Tahoma, Verdana; font-size: 7pt; color: #840000"><br /><br />' + txt + '</span>' : '') + '</div>';

  document.getElementById('baloonThumb').style.display = 'block';
  document.getElementById('baloonThumb').style.top = eval(offsetTop) + 'px';
  document.getElementById('baloonThumb').style.left = eval(offsetLeft) + 'px';
  document.getElementById('baloonThumb').innerHTML = '<a href="' + img.parentNode.href + '"><img src="' + img.src + '" style="border: 1px solid #333" onmouseout="hideBaloon();" alt="" /></a>';
}

function hideBaloon(id) {
  if(id) {
    document.getElementById(id).style.display = 'none';
  } else {
    document.getElementById('baloon').style.display = 'none';
    document.getElementById('baloonbg').style.display = 'none';
    document.getElementById('baloonThumb').style.display = 'none';
  }
}
<?
      break;
    case 'pngfix':
?>
function correctPNG() {
  // for(var i = 0; i < document.images.length; i++) {
    // var img = document.images[i];
    var img = document.getElementById('baloonbg');
    var imgName = img.src.toUpperCase();

    if (imgName.substring(imgName.length-3, imgName.length) == "PNG") {
      var imgID = (img.id) ? "id='" + img.id + "' " : "";
      var imgClass = (img.className) ? "class='" + img.className + "' " : "";
      var imgTitle = (img.title) ? "title='" + img.title + "' " : "title='" + img.alt + "' ";
      var imgStyle = 'display: inline-block;' + img.style.cssText;
      var imgAttribs = img.attributes;

      for (var j = 0; j < imgAttribs.length; j++) {
        var imgAttrib = imgAttribs[j];
        if (imgAttrib.nodeName == 'align') {      
          if (imgAttrib.nodeValue == 'left') imgStyle = 'float: left; ' + imgStyle;
          if (imgAttrib.nodeValue == 'right') imgStyle = 'float: right; ' + imgStyle;
          break;
        }
      }

      var strNewHTML = "<span " + imgID + imgClass + imgTitle;
      strNewHTML += " style=\"" + "width: " + img.width + "px; height: " + img.height + "px; " + imgStyle + ";";
      strNewHTML += "filter: progid:DXImageTransform.Microsoft.AlphaImageLoader";
      strNewHTML += "(src=\'" + img.src + "\', sizingMethod='scale');\"></span>" ;
      img.outerHTML = strNewHTML;
      // i = i-1;
    }
  // }
}

window.attachEvent('onload', correctPNG);
<?
      break;
    case 'transform':
?>
function transform(id, cid, mode) {
  document.getElementById('photo').src = '<?php echo(genURI('transform', '\' + cid + \'', '\' + id + \'', '\' + mode + \'')); ?>';
}
<?
      break;
  }
  echo('//]]>');
  exit;

/* ___IMAGES */

} elseif ($_i[0] == 'img') {
  header('Cache-Control: public, must-revalidate', false);
  switch($_i[1]) {
    case 'bg':
      header('Content-Type: image/gif');
      echo(base64_decode('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'));
      break;
    case 'baloon':
      header('Content-Type: image/png');
      echo(base64_decode('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'));
      break;
    case 'px':
      $c = @$_i[2];

      // some headers to prevent caching
      header('Content-type: image/gif');
      #header('Expires: Wed, 11 Nov 1998 11:11:11 GMT');
      header('Cache-Control: public'); // no-cache
      #header('Cache-Control: must-revalidate');

      // HEX -> RGB
      $r = hexdec(substr($c, 0, 2));
      $g = hexdec(substr($c, 2, 2));
      $b = hexdec(substr($c, 4, 2));

      // colored pixel
      if($c){
        printf('%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c',
        71,73,70,56,57,97,1,0,1,0,128,0,0,$r,$g,$b,0,0,0,44,0,0,0,0,1,0,1,0,0,2,2,68,1,0,59);
      // transparent pixel
      } else {
        printf('%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%c%',
        71,73,70,56,57,97,1,0,1,0,128,255,0,192,192,192,0,0,0,33,249,4,1,0,0,0,0,44,0,0,0,0,1,0,1,0,0,2,2,68,1,0,59);
      }
      break;
    default:
      header('Location: '.genURI());
      break;
  }
  exit;
}

/* ___INDEX */

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
  <head>
    <title><?php echo(htmlentities($conf['sitename']).(($_o) ? ' - '.escapeStr($_o) : null)); ?></title>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
    <meta http-equiv="imagetoolbar" content="no" />
    <meta name="author" content="Sijawusz Pur Rahnama [://sija.info/]" />
    <meta name="generator" content="photoindex v<?php echo($version); ?>" />

    <!-- fix the FOUC -->
    <script type="text/javascript"> </script>

    <link href="<?php echo(genURI('css')); ?>" type="text/css" rel="stylesheet" media="screen" />
    <style type="text/css" media="print">
      @import url(<?php echo(genURI('css')); ?>);
      #header {
        display: none;
      }
    </style>
  </head>
<?php if(!$conf['alt_baloon'] && $conf['baloons']) { ?>

  <!--[if IE]>
    <script type="text/javascript" src="<?php echo(genURI('js', 'pngfix')); ?>"></script>
  <![endif]-->
<?php } ?>

  <body xml:lang="en" lang="en">
    <script type="text/javascript" src="<?php echo(genURI('js', 'transform')); ?>"></script>
<?php if($conf['baloons']) { ?>
    <script type="text/javascript" src="<?php echo(genURI('js', 'baloon')); ?>"></script>

    <div id="baloonThumb"></div>
    <div id="baloon"></div>
<?php if($conf['alt_baloon']) { ?>
    <div id="baloonbg" class="baloonbg"></div>
<?php } else { ?>
    <img src="<?php echo(genURI('img', 'baloon', 'png')); ?>" id="baloonbg" style="position: absolute; display: none; width: 234px; height: 91px" alt="" />
<?php } ?>
<?php } ?>

    <div id="header">
      <div id="title">{<a href="<?php echo(genURI('show', $_p)); ?>" id="back" title="index [alt+x]" accesskey="x">&laquo;</a>} <?php echo(htmlentities($conf['sitename'])); ?></div>
    </div>
<?php if ((count($dirs)-1) > 0) { ?>
    <div id="dirs">
      <b>___dirs:</b> { <?php foreach($dirs AS $d => $v) echo((($d != min(array_flip($dirs))) ? ', ' : null).(($d == $_p) ? '<b>'.escapeStr($v).'</b>' : '<a href="'.genURI('show', $d).'" title="'.count($photos[$d]).' photo(s)">'.escapeStr($v).'</a>')); ?> }
      <?php if(count($dirs)) { ?><a href="<?php echo(genURI('random')); ?>" title="random photo from all albums" class="extras">?</a><?php } ?>

    </div>
<?php } ?>

    <div id="main">
      <div id="album"><?php echo((($_o) ? escapeStr($_o).(($_n > 2) ? '<a href="'.genURI('random', $_p).'" title="random [alt+r]" accesskey="r" id="random">?</a>' : null).' ['.$_n.']'.(($_j >= 0) ? ' / '.preg_replace('/(\.[a-z0-9]+$|[^a-z0-9 &;]+)/i', '<span class="nonalpha">\\1</span>', $_f['file']).'<a href="#transform" onclick="transform(\''.$_j.'\', \''.$_p.'\', \'bw\'); return(false);" title="black &amp; white [alt+b]" accesskey="b" class="extras">B</a><a href="#transform" onclick="transform(\''.$_j.'\', \''.$_p.'\', \'sepia\'); return(false);" title="sepia [alt+s]" accesskey="s" class="extras">S</a><a href="#transform" onclick="transform(\''.$_j.'\', \''.$_p.'\', \'invert\'); return(false);" title="invert [alt+i]" accesskey="i" class="extras">I</a>' : null) : null)); ?></div>

      <div id="photos">
        <table border="0" cellpadding="5" cellspacing="5">
          <tr>
            <td valign="top">
<?php if (!$conf['fullpic_alone'] || $_i[0] != 'show' || !$photos[$_p][$_i[2]]) { ?>
              <table border="0" cellspacing="0" cellpadding="2">
                <tr>
<?php
  for($i = 0; $i < $_n; $i++) {
    $photo = $photos[$_p][$i];
    echo(rptStr(' ', 17).'<td><a href="'.genURI('show', $_p, $i).'"><img src="'.$photo['thumb'].'" alt="'.$photo['file'].'" title="'.cutStr($photo['file'], 20).'"'.(($conf['baloons']) ? ' onmouseover="showBaloon(this.id, \''.htmlentities($photo['w'].' <b>x</b> '.$photo['h'].'<br /><b>'.$photo['size'].'</b> KiB<br /><br />'.$photo['time']).'\', \''.$conf['thumbs_side'].'\');"' : null).' id="photo-'.$i.'"/></a>'.(($conf['show_info']) ? '<br /><b>'.$photo['size'].'</b> <acronym title="kilobyte(s)">KiB</acronym>' : null).'</td>'."\n");

    if(($i % $conf['thumbs_in_line']) == $conf['thumbs_in_line']-1 && $photos[$_p][$i+1]) {
      echo(rptStr(' ', 15).'</tr><tr>'."\n");
    }
  }
?>
                </tr>
              </table>
              <br />
<?php
}

if ($_j >= 0) {
  echo(rptStr(' ', 13).(($photos[$_p][$_j+1]) ? '<a href="'.genURI('show', $_p, ($_j+1)).'" title="next ['.$photos[$_p][$_j+1]['file'].'] [alt+n]">' : null).'<img src="'.(($conf['watermarks']) ? $_f['watermark'] : (($dirs[$_p] == '.') ? $_ad : $_ad.'/'.$dirs[$_p]).'/'.rawurlencode(unhtmlentities($_f['file']))).'" alt="'.$_f['file'].'" id="photo" />'.(($photos[$_p][$_j+1]) ? '</a>' : null).'<div id="info"><b>'.$_f['size'].'</b> <acronym title="kilobyte(s)">KiB</acronym> / '.$_f['w'].' <b>x</b> '.$_f['h'].' / '.$_f['time'].'</div>');
}
?>

              <br /><br />
              <table border="0" width="100%">
                <tr>
                  <td align="left">
                    <?php echo(($photos[$_p][$_j-1]) ? '<b>[<a href="'.genURI('show', $_p, '0').'" title="first ['.$photos[$_p][0]['file'].'] [alt+f]" accesskey="f">&laquo;</a>]</b> <a href="'.genURI('show', $_p, ($_j-1)).'" title="previous ['.$photos[$_p][$_j-1]['file'].'] [alt+p]" accesskey="p">&lt; <u>p</u>rev</a>' : '&nbsp;'); ?>

                  </td>
                  <td align="right">
                    <?php echo(($photos[$_p][$_j+1]) ? '<a href="'.genURI('show', $_p, ($_j+1)).'" title="'.(($_j == -1) ? 'view' : 'next').' ['.$photos[$_p][$_j+1]['file'].'] [alt+n]" accesskey="n">'.(($_j == -1) ? 'view' : '<u>n</u>ext').' &gt;</a> <b>[<a href="'.genURI('show', $_p, ($_n-1)).'" title="last ['.$photos[$_p][($_n-1)]['file'].'] [alt+l]" accesskey="l">&raquo;</a>]</b>' : '&nbsp;'); ?>

                  </td>
                </tr>
              </table>
              <br />
            </td>
          </tr>
        </table>
      </div>
<?php
$timeparts = explode(' ', microtime());
$endtime = $timeparts[1].substr($timeparts[0], 1);
?>
      <div id="footer">
        Page generated in <b><?php echo(bcsub($endtime, $starttime, 3)); ?></b>s,
        powered by photoindex v<b><?php echo($version); ?></b>.<br />
        <span>&copy;</span>2004&#8212;<?php echo(date('Y')); ?> <a href="http://gibbon.pl/" title="[://gibbon.pl/]">Sijawusz Pur Rahnama</a>
      </div>
    </div>
  </body>
<!-- Script execution time: <?php echo(bcsub($endtime, $starttime, 6)); ?> -->
</html>