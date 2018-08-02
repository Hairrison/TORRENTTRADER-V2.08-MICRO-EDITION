<?php
//
//  TorrentTrader v2.x
//        $LastChangedDate: 2012-07-13 21:38:02 +0100 (Fri, 13 Jul 2012) $
//        $LastChangedBy: torrenttrader $
//
//        http://www.torrenttrader.org
//
//
require_once("backend/functions.php");

dbconn();

if ($_GET["passkey"]) {
        $CURUSER = mysql_fetch_assoc(SQL_Query_exec("SELECT * FROM users INNER JOIN groups ON users.class=groups.group_id WHERE passkey=".sqlesc($_GET["passkey"])." AND enabled='yes' AND status='confirmed'"));
}

//check permissions
if ($site_config["MEMBERSONLY"]){
        loggedinonly();
   
        if($CURUSER["can_download"]=="no")
                show_error_msg(T_("ERROR"), T_("NO_PERMISSION_TO_DOWNLOAD"), 1);
}

$id = (int)$_GET["id"];

if (!$id)
        show_error_msg(T_("ID_NOT_FOUND"), T_("ID_NOT_FOUND_MSG_DL"), 1);

$res = SQL_Query_exec("SELECT filename, banned, external, announce FROM torrents WHERE id =".intval($id));
$row = mysql_fetch_assoc($res);

$torrent_dir = $site_config["torrent_dir"];

$fn = "$torrent_dir/$id.torrent";

if (!$row)
        show_error_msg(T_("FILE_NOT_FOUND"), T_("ID_NOT_FOUND"),1);
if ($row["banned"] == "yes")
        show_error_msg(T_("ERROR"), T_("BANNED_TORRENT"), 1);
if (!is_file($fn))
        show_error_msg(T_("FILE_NOT_FOUND"), T_("FILE_NOT_FILE"), 1);
if (!is_readable($fn))
        show_error_msg(T_("FILE_NOT_FOUND"), T_("FILE_UNREADABLE"), 1);

$name = $row['filename'];
$friendlyurl = str_replace("http://","",$site_config["SITEURL"]);
$friendlyname = str_replace(".torrent","",$name);
$friendlyext = ".torrent";
$name = $friendlyname ."[". $friendlyurl ."]". $friendlyext;

SQL_Query_exec("UPDATE torrents SET hits = hits + 1 WHERE id = $id");

require_once("backend/BEncode.php");
require_once("backend/BDecode.php");

//if user dont have a passkey generate one, only if tracker is set to members only
if ($site_config["MEMBERSONLY"]){
        if (strlen($CURUSER['passkey']) != 32) {
                $rand = array_sum(explode(" ", microtime()));
                $CURUSER['passkey'] = md5($CURUSER['username'].$rand.$CURUSER['secret'].($rand*mt_rand()));
                SQL_Query_exec("UPDATE users SET passkey='$CURUSER[passkey]' WHERE id=$CURUSER[id]");
        }
}

if ($row["external"]!='yes' && $site_config["MEMBERSONLY"]){// local torrent so add passkey
        $dict = BDecode(file_get_contents($fn));
        $dict['announce'] = sprintf($site_config["PASSKEYURL"], $CURUSER["passkey"]);
        unset($dict['announce-list']);

        $data = BEncode($dict);
   
        header('Content-Disposition: inline; attachment; filename="'.$name.'"');

        //header('Content-Length: ' . strlen($data));
   
        header("Content-Type: application/x-bittorrent");

        print $data;

}else{// external torrent so no passkey needed

   $data[ ] = Array( sprintf( $site_config['PASSKEYURL'], $CURUSER['passkey'] ) );
  
   $dict = BDecode( file_get_contents( $fn ) );
  
   if ( ! ( is_array( $dict[ 'announce-list' ] ) ) )
   {
                $dict[ 'announce-list' ][ ] = Array( $dict[ 'announce' ] );
   }
  
   $dict[ 'announce-list' ] = array_reverse( array_merge( $dict[ 'announce-list' ], $data ) );

   header('Content-Disposition:  attachment; filename="'.$name.'"');
  
   header("Content-Type: application/x-bittorrent");
                           
   echo BEncode($dict);
}

mysql_close();
?>