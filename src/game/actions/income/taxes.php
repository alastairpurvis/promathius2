<?
// Check that the script is not being accessed directly
if ( !defined('PROMATHIUS') )
{
	die("Hacking attempt");
}

// killUnits(unit,0.10,0.30,3) will destroy 30%-90% of unit
// use multiple rolls to get numbers closer to the middle
function killUnits ($type, $minpc, $maxpc, $rolls, $troopnum = false) {
	global $users;
	$losspct = 0;
	$min = round(1000000 * $minpc);
	$max = round(1000000 * $maxpc);
	for ($i = 0; $i < $rolls; $i++)
		$losspct += mt_rand($min,$max);
	$losspct /= 1000000;
	if($type == 'troop') {
		$loss = round($users[troop][$troopnum] * $losspct);
		$users[troop][$troopnum] -= $loss;
	}
	else {
		$loss = round($users[$type] * $losspct);
		$users[$type] -= $loss;
	}
	return $loss;
}

if($do_notes) {
	$users['notes'] = $upd_notes;
	saveUserData($users, "notes", true);
	echo "Notepad updated!<hr>";
}

if($reset_basehref) {
	$users['basehref'] = $config['sitedir'];
	saveUserData($users, 'basehref');
	endScript('Style directory reset!');
}


if($leave_protection) {
	
	$REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
	$users[email] = mysql_real_escape_string($users[email]);
	$old_accounts = mysql_safe_query("SELECT * FROM $playerdb WHERE ((email='$users[email]' OR IP='$REMOTE_ADDR') AND disabled!=2 AND num!=$users[num]) ORDER BY idle DESC;");
	
	$test = mysql_fetch_array($old_accounts);

	if($test[idle] > (time() - 24 * 60 * 60))
		endScript("A previous account with your IP or Email was too recently logged in for you to leave protection.");

	if($users['turnsused'] > $config['protection'])
		endScript("You are already out of protection...");
	$users['turnsused'] = $config['protection'] + 1;
	saveUserData($users, 'turnsused');
	endScript("You have left protection early, and now cannot return!");
}

if($do_profile) {
	$stuff = 'aim msn profile email';
	$stuffarr = explode(" ", $stuff);

	if(!(eregi("^[_+A-Za-z0-9-]+(\\.[_+A-Za-z0-9-]+)*@[A-Za-z0-9-]+(\\.[A-Za-z0-9-]+)*$",$email,$matches)))
		endScript("Invalid email address!");

	foreach($stuffarr as $var) {
		$users[$var] = $$var;
	}
#	$users[profile] = str_replace(' ', chr(26), $users[profile]);
#	$users[profile] = wordwrap($users[profile], 75, "\n", 1);
#	$users[profile] = str_replace(chr(26), ' ', $users[profile]);
#	$users[profile] = preg_replace('([\S]{75,})', '', $users[profile]);
	$users[profile] = preg_replace('([\w]{75,})', '', $users[profile]);
	saveUserData($users, $stuff, true);
}


if (($do_polymorph) && ($yes_polymorph))
{
	if ($users[turns] < $config[initturns])
		endScript("You don't have enough turns!");
	if ($users[health] < 75)
		endScript("You don't have enough health!");
	if ($users[wizards] < $users[land]*3)
		endScript("You don't have enough $uera[wizards]!");
	if ($new_race != $users[race] && !empty($new_race))
	{
		$users[health] -= 50;
		$users[turns] -= $config[initturns];
		$display_str = 'Your race has been changed!<br />';
		foreach($config[troop] as $num => $mktcost) {
			$display_str .= commas(killUnits(troop,0.10,0.15,1,$num)).' '.$uera["troop$num"].', ';
		}
		$display_str .= commas(killUnits(peasants,0.15,0.20,1))." $uera[peasants], and ".
				commas(killUnits(wizards,0.10,0.15,1))." $uera[wizards] died in the revolution.<br />\n";

		$buildloss = 0;
		$buildloss += killUnits(homes,0.08,0.27,2);
		$buildloss += killUnits(shops,0.08,0.27,2);
		$buildloss += killUnits(industry,0.08,0.27,2);
		$buildloss += killUnits(barracks,0.08,0.27,2);
		$buildloss += killUnits(labs,0.08,0.27,2);
		$buildloss += killUnits(farms,0.08,0.27,2);
		$buildloss += killUnits(towers,0.08,0.27,2);
		$users[freeland] += $buildloss;
		$size = calcSizeBonus($users[networth]);
		$display_str .= commas($buildloss)." structures, ".
				commas(killUnits(food,0.05*$size,0.15*$size,3))." $uera[food], ".
				commas(killUnits(runes,0.05*$size,0.15*$size,3))." $uera[runes], and $".
				commas(killUnits(cash,0.05*$size,0.15*$size,3))." were lost during the chaos.\n";
		$users[race] = $new_race;
		$urace = loadRace($users[race], $users[region]);
		$users[networth] = getGreatness($users);
		$tpl->assign('printmessage', $display_str);
		saveUserData($users,"networth troops wizards homes shops industry barracks labs farms towers freeland food runes cash turns health race");
	}
}
if ($do_changetax)
{
	fixInputNum($new_tax);
	if ($new_tax < 5)
	{
	mysql_query("UPDATE ".$config['prefixes'][1]."_players SET new_error='The tax rate cannot be set to such a low amount.' WHERE num='$users[num]';");
	$uri = urldecode($_SERVER['REQUEST_URI']);
	$action = substr($uri, strpos($uri, '?') + 1);
	header("Location: ?" . $action); 
	endScript();
	}
	if ($new_tax > $config['MaxTax'])
	{
			mysql_query("UPDATE ".$config['prefixes'][1]."_players SET new_error='You cannot set the tax rate to such an outrageously high amount.' WHERE num='$users[num]';");
	$uri = urldecode($_SERVER['REQUEST_URI']);
	$action = substr($uri, strpos($uri, '?') + 1);
	header("Location: ?" . $action); 
	endScript();
	}
	$users[tax] = $new_tax;
	saveUserData($users,"tax");
	$tpl->assign('printmessage', 'Tax rate updated to ' . $users[tax] . '%');
}
if ($do_changestyle)
{
	$users[style] = $color_setting;
	saveUserData($users,"style");
	header(str_replace('&amp;', '&', "Location: ?manage$authstr"));
	die();
}
if ($do_changeindustry)
{
	$total = 0;
	foreach($config[troop] as $num => $mktcost) {
		$ind[$num] = $_POST["ind_troop$num"];
		fixInputNum($ind[$num]);
		$total += $ind[$num];
		$users[ind][$num] = $ind[$num];
	}

	// they are all positive, right? [fixinputnum ;)]
	// also, if any exceed 100, total will exceed 100
	// thus...
	if($total > 100)
		endScript("Cannot set training that high!");

	// if we got here okay, we can save
	saveUserData($users,"production");
	$tpl->assign('printmessage', 'Training settings updated!');
}
if ($do_changepass)
{
	if (($new_password) && ($new_password == $new_password_verify)) {
		$users[password] = md5($new_password);
		mysql_safe_query("UPDATE $playerdb SET password='$users[password]' WHERE num=$users[num];");
		mysql_safe_query("UPDATE ".$prefix."_users SET user_password='$users[password]' WHERE empire_id=$users[num];");
		endScript("Password changed. Please logout and login again.");
	}
	else	$tpl->assign('printmessage', "Error! Passwords do not match!<br />\n");
}
if (($do_setvacation) && ($yes_vacation))
{
	if ($lastweek)
		endScript("Vacation disabled during last week of game!");
	$users[vacation] = 1;
	SaveUserData($users,"vacation");
	endScript("Vacation setting saved; your account is now locked. Your empire will be frozen in $config[vacationdelay] hours.");
}

if($lastweek == true)
	$tpl->assign('lastweek', 'true');
if($users['turnsused'] < $config['protection'])
	$tpl->assign('protection', 'true');

$base = '';
$base = str_replace("file:///", "", $users['basehref']);
$base = str_replace("//", "\\", $base);
$base = str_replace("/", "\\", $base);

$tpl->assign('ubase', $base);
$tpl->assign('initturns', $config['initturns']);
$tpl->assign('land', $users['land']);
$tpl->assign('wizards', $uera[wizards]);
$troopnames = array();
$uind = array();
$numbers = array();
foreach($config[troop] as $num => $mktcost) {
	$an = $num;
	$troopnames[$an] = $uera["troop$num"];
	$uind[$an] = $users[ind][$num];
	$numbers[$an] = $num;
}
$tpl->assign('troops', $troopnames);
$tpl->assign('users', $users);
$tpl->assign('uind', $uind);
$tpl->assign('numbers', $numbers);
$tpl->assign('minvacation', $config['minvacation']);
$tpl->assign('vacationdelay', $config['vacationdelay']);
$tpl->assign('tax', $users['tax']);
$tpl->assign('email', $users['email']);
$tpl->assign('aim', $users['aim']);
$tpl->assign('msn', $users['msn']);
$tpl->assign('profile', str_replace("\n", "", $users['profile']));
$tpl->assign('igname', $users['igname']);
$tpl->assign('notes', $users['notes']);

foreach($rtags as $id => $race)
	$racearray[] = array('id' => $id, 'name' => $race);
$tpl->assign('races', $racearray);

$stylearray = array();
foreach($stylenames as $i => $name)
	if(empty($adminstyles[$i]) || $users[disabled] == 2)
		$stylearray[] = array('id' => $i, 'name' => $name);
$tpl->assign('styles', $stylearray);

// Load the game graphical user interface
initGUI();
include($game_root_path."/lib/error_msg.php");
$tpl->display('actions/income/taxes.tpl');
?>
