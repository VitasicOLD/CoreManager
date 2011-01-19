<?php
/*
    CoreManager, PHP Front End for ArcEmu, MaNGOS, and TrinityCore
    Copyright (C) 2010-2011  CoreManager Project

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


require_once "header.php";

//#############################################################################
// Login
//#############################################################################
function dologin()
{
  global $corem_db, $sql, $core;

  if ( empty($_POST["login"]) || empty($_POST["password"]) )
    redirect('login.php?error=2');

  $user_name  = $sql["mgr"]->quote_smart($_POST["login"]);
  $user_pass  = $sql["mgr"]->quote_smart($_POST["password"]);

  if ( ( strlen($user_name) > 255 ) || ( strlen($user_pass) > 255 ) )
    redirect('login.php?error=1');

  // Users may log in using either their username or screen name
  // check for matching login
  if ( $core == 1 )
    $query = "SELECT * FROM accounts WHERE login='".$user_name."' AND password='".$user_pass."'";
  else
  {
    $pass_hash = sha1(strtoupper($user_name.":".$user_pass));
    $query = "SELECT * FROM account WHERE username='".$user_name."' AND sha_pass_hash='".$pass_hash."'";
  }

  $name_result = $sql["logon"]->query($query);
  if ( !$sql["logon"]->num_rows($name_result) )
  {
    // if we didn't find one, check for matching screen name
    $query = "SELECT * FROM config_accounts WHERE ScreenName='".$user_name."'";
    $name_result = $sql["mgr"]->query($query);
    if ($sql["mgr"]->num_rows($name_result))
    {
      $name = $sql["mgr"]->fetch_assoc($name_result);
      $user_name = $name["Login"];
    }
  }
  else
  {
    // we'll still need the screen name if we have one
    $query = "SELECT * FROM config_accounts WHERE Login='".$user_name."'";
    $name_result = $sql["mgr"]->query($query);
    $name = $sql["mgr"]->fetch_assoc($name_result);
  }
  // if we didn't find the name given for either entries, then the name will come up bad below

  if ( $core == 1 )
    $query = "SELECT * FROM accounts WHERE login='".$user_name."' AND password='".$user_pass."'";
  else
  {
    $pass_hash = sha1(strtoupper($user_name.":".$user_pass));
    $query = "SELECT * FROM account WHERE username='".$user_name."' AND sha_pass_hash='".$pass_hash."'";
  }

  $result = $sql["logon"]->query($query);
  $s_result = $sql["mgr"]->query("SELECT SecurityLevel AS gm FROM config_accounts WHERE Login='".$user_name."'");
  $temp = $sql["mgr"]->fetch_assoc($s_result);

  if ( $temp["gm"] == NULL )
    $temp["gm"] = 0;

  $_SESSION["gm_lvl"] = $temp["gm"];

  //we need this later
  //unset($user_name);

  if ( $sql["logon"]->num_rows($result) == 1 )
  {
    if ( $core == 1 )
      $acct = $sql["logon"]->result($result, 0, 'acct');
    else
      $acct = $sql["logon"]->result($result, 0, "id");

    if ( $core == 1 )
      $ban_query = "SELECT banned AS unbandate, banreason FROM accounts WHERE login='".$user_name."' AND password='".$user_pass."'";
    else
      $ban_query = "SELECT unbandate, banreason FROM account_banned WHERE id='".$acct."' AND active=1";

    $ban_result = $sql["logon"]->query($ban_query);
    $ban = $sql["logon"]->fetch_assoc($ban_result);

    if ( $sql["logon"]->num_rows($ban_result) != 0 )
    {
      $info = lang("login", "ban_reason")." ".$ban["banreason"];
      $info .= "/br/"; // because we do a little XSS prevention, we'll replace this with <br /> below
      $info .= lang("login", "unbandate")." ".date("g:i A", $ban["unbandate"])." on ".date("d-M-Y", $ban["unbandate"]);
      redirect("login.php?error=3&info=".$info);
    }
    else
    {
      $_SESSION["user_id"] = $acct;
      if ( $core == 1 )
        $_SESSION["login"] = $sql["logon"]->result($result, 0, "login");
      else
        $_SESSION["login"] = $sql["logon"]->result($result, 0, "username");
      // if we got a screen name, we'll want it later.
      $_SESSION["screenname"] = $name["ScreenName"];
      //gets our numerical level based on Security Level.
      $_SESSION["user_lvl"] = gmlevel($temp["gm"]);
      $_SESSION["realm_id"] = $sql["logon"]->quote_smart($_POST["realm"]);
      $_SESSION["client_ip"] = ( ( isset($_SERVER["REMOTE_ADDR"]) ) ? $_SERVER["REMOTE_ADDR"] : getenv("REMOTE_ADDR") );
      $_SESSION["logged_in"] = true;

      if ( isset($_POST["remember"]) && $_POST["remember"] != '' )
      {
        setcookie(   "login", bin2hex(gzcompress($_SESSION["login"])), time() + 60*60*24*30);
        setcookie("password", bin2hex(gzcompress($user_pass)), time() + 60*60*24*30);
        setcookie("realm_id", $_SESSION["realm_id"], time() + 60*60*24*30);
      }
      redirect("index.php");
    }
  }
  else
  {
    redirect("login.php?error=1");
  }
}


//#################################################################################################
// Print login form
//#################################################################################################
function login()
{
  global $output, $characters_db, $server, $remember_me_checked, $sql, $core, $site_encoding;
  
  $override_remember_me = $_COOKIE["override_remember_me"];
  // if the cookie doesn't exist, we default to showing
  if ( !isset($override_remember_me) )
    $override_remember_me = 1;

  $output .= '
          <center>
            <script type="text/javascript" src="libs/js/sha1.js"></script>
            <script type="text/javascript">
              // <![CDATA[
                function dologin ()
                {
                  document.form.password.value = document.form.login_pass.value;
                  do_submit();
                }
              // ]]>
            </script>
            <div class="half_frame fieldset_border">
              <span class="legend">'.lang("login", "login").'</span>
              <form method="post" action="login.php?action=dologin" name="form" onsubmit="return dologin()">
                <input type="hidden" name="password" value="" maxlength="256" />
                <table class="hidden" id="login_table">
                  <tr>
                    <td colspan="3">
                      <hr />
                    </td>
                  </tr>
                  <tr>
                    <td align="right">'.lang("login", "username").' :</td>
										<td>&nbsp;</td>
                    <td align="left">
                      <input type="text" name="login" size="24" maxlength="16" onfocus="this.select()" />
                    </td>
                  </tr>
                  <tr>
                    <td align="right">'.lang("login", "password").' :</td>
										<td>&nbsp;</td>
                    <td align="left">
                      <input type="password" name="login_pass" size="24" maxlength="40" />
                    </td>
                  </tr>';

  $result = $sql["mgr"]->query('SELECT `Index` AS id, Name AS name FROM config_servers LIMIT 10');

  if ( ( $sql["mgr"]->num_rows($result) > 1 ) && ( count($server) > 1 ) && ( count($characters_db) > 1 ) )
  {
    $output .= '
                  <tr align="right">
                    <td>'.lang("login", "select_realm").' :</td>
										<td>&nbsp;</td>
                    <td align="left">
                      <select name="realm" id="login_realm">';
    while ( $realm = $sql["mgr"]->fetch_assoc($result) )
      if ( isset($server[$realm["id"]]) )
        $output .= '
                        <option value="'.$realm["id"].'" '.( $_SESSION["realm_id"] == $realm["id"] ? 'selected="selected"' : '' ).'>'.htmlentities($realm["name"], ENT_COMPAT, $site_encoding).'</option>';
    $output .= '
                      </select>
                    </td>
                  </tr>';
  }
  else
    $output .= '
                  <!-- input type="hidden" name="realm" value="1" / -->
                  <input type="hidden" name="realm" value="'.$sql["mgr"]->result($result, 0, 'id').'" />';
  $output .= '
                  <!-- tr>
                    <td>
                    </td>
                  </tr -->
                  <tr>
                    <td align="right">'.lang("login", "remember_me").' : </td>
										<td>&nbsp;</td>
                    <td align="left"><input type="checkbox" name="remember" value="1"';
  if ( $remember_me_checked && $override_remember_me )
    $output .= ' checked="checked"';
  // this_is_junk: the hardcoded CSS here is for an input doing nothing.
  $output .= ' /></td>
                  </tr>
                  <tr>
                    <td>
                    </td>
                  </tr>
                  <tr align="right">
                    <td colspan="3">
                      <input type="submit" value="" style="display:none" />';
  makebutton(lang("login", "not_registrated"), 'register.php" type="wrn', 130);
  makebutton(lang("login", "login"), 'javascript:dologin()" type="def', 130);
  $output .= '
                    </td>
                  </tr>
                  <tr align="center">
                    <td colspan="3"><a href="register.php?action=pass_recovery">'.lang("login", "pass_recovery").'</a></td>
                  </tr>
                  <tr>
                    <td colspan="3">
                      <hr />
                    </td>
                  </tr>
                </table>
                <script type="text/javascript">
                  // <![CDATA[
                    document.form.login.focus();
                  // ]]>
                </script>
              </form>
              <br />
            </div>
            <br /><br />
          </center>';
}


//#################################################################################################
// Login via set cookie
//#################################################################################################
function do_cookie_login()
{
  global $corem_db, $sql, $core;

  if ( empty($_COOKIE["login"]) || empty($_COOKIE["password"]) || empty($_COOKIE["realm_id"]) )
    redirect("login.php?error=2");

  $user_name = $sql["logon"]->quote_smart($_COOKIE["login"]);
  $user_pass = $sql["logon"]->quote_smart($_COOKIE["password"]);

  // see if our login & password cookies are old or new form...
  if ( !preg_match("/^([A-Fa-f0-9]{2})*$/", $user_name) )
  {
    // we have old form cookies, we'll rebuild them to new form and go from there
    setcookie("login",    bin2hex(gzcompress($user_name)), time() + 60*60*24*30);
    setcookie("password", bin2hex(gzcompress($user_pass)), time() + 60*60*24*30);

    redirect("login.php");
  }
  else
  {
    // we have new form cookies
    $user_name = ( ( isset($_COOKIE["login"]) ) ? gzuncompress(pack("H*" , $_COOKIE["login"])) : NULL );
		$user_pass = ( ( isset($_COOKIE["password"]) ) ? gzuncompress(pack("H*" , $_COOKIE["password"])) : NULL );
  }

  // Users may log in using either their username or screen name
  // check for matching login
  if ( $core == 1 )
    $query = "SELECT * FROM accounts WHERE login='".$user_name."'";
  else
    $query = "SELECT * FROM account WHERE username='".$user_name."'";

  $name_result = $sql["logon"]->query($query);

  if ( !$sql["logon"]->num_rows($name_result) )
  {
    // if we didn't find one, check for matching screen name
    $query = "SELECT * FROM config_accounts WHERE ScreenName='".$user_name."'";
    $name_result = $sql["mgr"]->query($query);

    if ( $sql["mgr"]->num_rows($name_result) )
    {
      $name = $sql["mgr"]->fetch_assoc($name_result);
      $user_name = $name["Login"];
    }
  }
  else
  {
    // we'll still need the screen name if we have one
    $query = "SELECT * FROM config_accounts WHERE Login='".$user_name."'";
    $name_result = $sql["mgr"]->query($query);
    $name = $sql["mgr"]->fetch_assoc($name_result);
  }
  // if we didn't find the name given for either entries, then the name will come up bad below
  
  if ( $core == 1 )
    $query = "SELECT * FROM accounts WHERE login='".$user_name."' AND password='".$user_pass."'";
  else
  {
    $pass_hash = sha1(strtoupper($user_name.":".$user_pass));
    $query = "SELECT *, username AS login FROM account WHERE username='".$user_name."' AND sha_pass_hash='".$pass_hash ."'";
  }

  $result = $sql["logon"]->query($query);

  $s_result = $sql["mgr"]->query("SELECT SecurityLevel AS gm FROM config_accounts WHERE login='".$user_name."'");
  $temp = $sql["mgr"]->fetch_assoc($s_result);
  $_SESSION["gm_lvl"] = $temp["gm"];

  /*$result = $sql["logon"]->query('SELECT login, gm, acct FROM accounts WHERE login = \''.$user_name.'\' AND password = \''.$user_pass.'\'');
  $temp = $sql["logon"]->fetch_assoc($result);
  //store the ArcEmu value for other functions
  $_SESSION["gm_lvl"] = $temp["gm"];*/


  //we need these later
  //unset($user_name);
  //unset($user_pass);

  if ( $sql["logon"]->num_rows($result) )
  {
    if ( $core == 1)
      $acct = $sql["logon"]->result($result, 0, "acct");
    else
      $acct = $sql["logon"]->result($result, 0, "id");
    
    if ( $core == 1 )
      $ban_query = "SELECT COUNT(*) FROM accounts WHERE acct='".$acct."' AND banned='1'";
    else
      $ban_query = "SELECT COUNT(*) FROM account_banned WHERE id='".$acct."' AND active='1'";

    if ( $sql["logon"]->result($sql["logon"]->query($ban_query), 0) )
      redirect("login.php?error=3");
    else
    {
      $_SESSION["user_id"] = $acct;
      $_SESSION["login"] = $sql["logon"]->result($result, 0, 'login');
      // if we got a screen name, we'll want it later.
      $_SESSION["screenname"] = $name["ScreenName"];
      //gets our numerical level based on ArcEmu level.
      $_SESSION["user_lvl"] = gmlevel($temp["gm"]);
      $_SESSION["realm_id"] = $sql["logon"]->quote_smart($_COOKIE["realm_id"]);
      $_SESSION["client_ip"] = ( ( isset($_SERVER["REMOTE_ADDR"]) ) ? $_SERVER["REMOTE_ADDR"] : getenv("REMOTE_ADDR") );
      $_SESSION["logged_in"] = true;
      redirect("index.php");
    }
  }
  else
  {
    setcookie (   "login", "", time() - 3600);
    setcookie ("realm_id", "", time() - 3600);
    setcookie ("password", "", time() - 3600);

    redirect("login.php?error=1");
  }
}


//#################################################################################################
// MAIN
//#################################################################################################
if ( isset($_COOKIE["login"]) && isset($_COOKIE["password"]) && isset($_COOKIE["realm_id"]) && empty($_GET["error"]) )
  do_cookie_login();

$err = ( ( isset($_GET["error"]) ) ? $_GET["error"] : NULL );
$info = ( ( isset($_GET["info"]) ) ? $_GET["info"] : NULL );

$output .= '
      <div class="bubble">
          <div class="top">';

if ( $err == 1 )
  $output .=  '
            <h1><font class="error">'.lang("login", "bad_pass_user").'</font></h1>';
elseif ( $err == 2 )
  $output .=  '
            <h1><font class="error">'.lang("login", "missing_pass_user").'</font></h1>';
elseif ( $err == 3 )
  $output .=  '
            <h1><font class="error">'.lang("login", "banned_acc").'</font></h1>';
  if ( isset($info) )
  {
    $info = htmlspecialchars($info);
    $info = str_replace("/br/", "<br />", $info);
    $output .= '<h1>'.$info.'</h1>';
  }
elseif ( $err == 5 )
{
  $output .=  '
            <h1><font class="error">'.lang("login", "no_permision").'</font></h1>';
  if ( isset($info) )
    $output .= '<h1><font class="error">'.lang("login", "req_permision").': '.$info.'</font></h1>';
}
elseif ( $err == 6 )
  $output .=  '
            <h1><font class="error">'.lang("login", "after_registration").'</font></h1>';
elseif ( $err == 7 )
  $output .=  '
            <h1><font class="error">'.lang("login", "after_activation").'</font></h1>';
elseif ( $err == 8 )
  $output .=  '
            <h1><font class="error">'.lang("login", "confirm_sent").'</font></h1>';
else
  $output .=  '
            <h1>'.lang("login", "enter_valid_logon").'</h1>';

unset($err);

$output .= '
          </div>';

$action = ( ( isset($_GET["action"]) ) ? $_GET["action"] : NULL );

if ( $action === "dologin" )
  dologin();
else
  login();

unset($action);
unset($action_permission);

require_once "footer.php";


?>
