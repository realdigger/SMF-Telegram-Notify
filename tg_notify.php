<?php
if (!defined('SMF'))
	die('No direct access...');

function tg_notify_adminAreas(&$areas)
{
	global $txt;
	loadLanguage('tg_notify');
	$areas['config']['areas']['modsettings']['subsections']['tg_notify'] = array($txt['tg_notify_admin']);
}

function tg_notify_modifyModifications(&$sub_actions)
{
	$sub_actions['tg_notify'] = 'tg_notify_settings';
}

function tg_notify_settings(&$return_config = false)
{
	global $context, $txt, $scripturl, $modSettings;
	
	loadLanguage('tg_notify');
	$context['page_title'] = $txt['tg_notify_admin'];
	$context['settings_title'] = $txt['tg_notify_admin'];
	$context['settings_message'] = $txt['tg_notify_admin_desc'];
	$context['post_url'] = $scripturl . '?action=admin;area=modsettings;save;sa=tg_notify';


	$config_vars = array(
		array('check', 'tg_notify_enable'),
		array('text', 'tg_notify_token','size' => 60),
		array('text', 'tg_notify_botname',),
	);


	if ($return_config)
		return $config_vars;

	if (isset($_GET['save'])) {
		checkSession();
		saveDBSettings($config_vars);
		redirectexit('action=admin;area=modsettings;sa=tg_notify');
	}
	prepareDBSettingContext($config_vars);
}


function tg_notify_actions(&$actionArray)
{
	$actionArray['tg_link'] = array('tg_notify.php', 'tg_link');
	$actionArray['tg_bot'] = array('tg_notify.php', 'tg_bot');
}

function tg_notify_user_setting(&$alert_types, &$group_options)
{
	global $context;
	loadLanguage('tg_notify');
	
	$memID=isset($_REQUEST['u'])?(int)$_REQUEST['u']:$context['user']['id'];
	
	$context['member']=array_merge(
		$context['member'],
		array('tg_notify' => isset($context['alert_prefs']['tg_notify']) ? $context['alert_prefs']['tg_notify'] : 0)	
	);

	if (isset($_POST['tg_notify'])){
		$update_prefs['tg_notify'] = $context['member']['tg_notify'] = (int) $_POST['tg_notify'];
		setNotifyPrefs($memID, $update_prefs);
	}
}


function tg_notify_integrate_buffer($buffer){
	global $modSettings, $txt, $context, $smcFunc;
	if(empty($modSettings['tg_notify_enable']) || empty($modSettings['tg_notify_token']))
		return $buffer;
	
	if(!isset($_REQUEST['area']) || $_REQUEST['area']!='notification')
		return $buffer;

	loadLanguage('tg_notify');
	
	//меняем текст
	$pattern ='%(label for=\"notify_announcements\">)(.*)(</dt>)%isU';
	$replacement = '$1$2'.$txt['tg_notify_user_set'].'<br>(<a href='.$GLOBALS['boardurl'].'/index.php?action=tg_link>'.$txt['tg_notify_user_set_link'].'</a>)$3';
	$buffer = preg_replace($pattern, $replacement, $buffer);
	
	//меняем контрол
	$options ='<option '.(empty($context['member']['notify_announcements']) ? 'selected ' : '').'value="0">'.$txt['tg_notify_select_none'].'</option>';
	if(!empty($context['member']['notify_announcements'])){
		if($context['member']['tg_notify']!="2" && $context['member']['tg_notify']!="3")
			$options.='
			<option selected value="1">'.$txt['tg_notify_select_email'].'</option>';
		else
			$options.='
			<option value="1">'.$txt['tg_notify_select_email'].'</option>';
	}
	else
		$options.='
			<option value="1">'.$txt['tg_notify_select_email'].'</option>';
	
	//проверяем наличие телеграма у юзера
	$memID=isset($_REQUEST['u'])?(int)$_REQUEST['u']:$context['user']['id'];
	$request = $smcFunc['db_query']('', '
			SELECT memID
			FROM {db_prefix}telegram
			WHERE memID = {int:memID}
			LIMIT 1',
			array(
				'memID' => $memID,
			)
		);
	if($smcFunc['db_num_rows']($request) != 1){
		$options.='
			<option disabled value="2">'.$txt['tg_notify_select_tg'].'</option>';
			
		$options.='
			<option disabled value="3">'.$txt['tg_notify_select_both'].'</option>';
	}
	else{	
		$options.='
			<option '.(!empty($context['member']['tg_notify']) && $context['member']['tg_notify'] == "2" ? 'selected ' : '').'value="2">'.$txt['tg_notify_select_tg'].'</option>';
			
		$options.='
			<option '.(!empty($context['member']['tg_notify']) && $context['member']['tg_notify'] == "3" ? 'selected ' : '').'value="3">'.$txt['tg_notify_select_both'].'</option>';
	}
	$smcFunc['db_free_result']($request);
	
	$pattern ='%(<input type=\"hidden\" name=\"notify_announcements\")(.*)(</dd>)%isU';
	$replacement = '
		<input type="hidden" id="notify_announcements" name="notify_announcements" value="0">
		<select name="tg_notify" onchange="if (this.selectedIndex==0) document.getElementById(\'notify_announcements\').value=\'0\'; else  document.getElementById(\'notify_announcements\').value=\'1\';">
			'.$options.'
		</select>
	';
	
	$replacement .= '$3';

	$buffer = preg_replace($pattern, $replacement, $buffer);
	
	return ($buffer);
}

function tg_notify_outgoing_email(&$subject, &$message, &$headers, &$to_array){

	global $smcFunc, $modSettings, $sourcedir;

	if(empty($modSettings['tg_notify_enable']) || empty($modSettings['tg_notify_token']))
		return;

	
	require_once($sourcedir . '/Subs-Notify.php');
	
	$member_list = array();
	$request = $smcFunc['db_query']('', '
		SELECT id_member, email_address, chatID
		FROM {db_prefix}members
		RIGHT JOIN {db_prefix}telegram ON id_member = memID
		WHERE email_address IN ({array_string:to_array})',
		array(
			'to_array' => $to_array,
		)
	);
	while ($row = $smcFunc['db_fetch_assoc']($request))
		$member_list[$row['id_member']] = $row;
	
	$smcFunc['db_free_result']($request);

	foreach ($member_list as $member){
			$prefs = getNotifyPrefs($member['id_member'], 'tg_notify', false);
			$tg_notify = (int)$prefs[$member['id_member']]['tg_notify'];
					
			if(!empty($tg_notify) && $tg_notify>1){
				//$matches[1] это from извлечённый из заголовков
				preg_match('/Reply-To: <(.*)>/',$headers , $matches);
				if (!empty($matches[1])){
					$title=$matches[1].$txt['tg_notify_msg_tit'].$subject."\x0D\x0A\x0D\x0A";
				}
				else{
					$title='';
				}
				
				tg_send($member['chatID'], $title.$message);
			}
			if($tg_notify==2){
				if(($key = array_search($member['email_address'], $to_array)) !== false){
					unset($to_array[$key]);
				}
			}		
	}
}

function tg_send($chat_id, $text)
{
	global $modSettings;
	
	if(empty($modSettings['tg_notify_enable']) || empty($modSettings['tg_notify_token']))
		return;
	
	$text=strip_tags($text,'<strong>');
	
	$message = array(
			'chat_id' => $chat_id,
			'text' => $text,
			);

	sleep(2);
	$ch = curl_init('https://api.telegram.org/bot' . $modSettings['tg_notify_token'] . '/sendMessage');  
	curl_setopt($ch, CURLOPT_POST, 1);  
	curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, false);
	curl_exec($ch);
	curl_close($ch);

}

function tg_antibf($chatID,$hit=null)
{
	global $txt, $smcFunc;
	loadLanguage('tg_notify');
	
	
	$request = $smcFunc['db_query']('', '
			SELECT hit, date
			FROM {db_prefix}tg_notify_antibf
			WHERE chatID = {int:chatID}
			LIMIT 1',
			array(
				'chatID' => $chatID,
			)
		);
		
	if(	$row = $smcFunc['db_fetch_assoc']($request)){
		//новый день, обнуляем попытки
		if($row['date']!=date("Ymd")){
			$smcFunc['db_query']('','
				DELETE FROM {db_prefix}tg_notify_antibf
				WHERE chatID = {int:chatID}',
				array(
					'chatID' => $chatID,
				)
			);
		}
		else{
		//попытки кончились?
			if($row['hit']>4){
				tg_send($chatID, $txt['tg_bot_attempts_ended']);
				die();
			}
		}
	}
	$smcFunc['db_free_result']($request);
	
	//неправильный ключ
	if(!empty($hit)){
		if(empty($row['hit']))
			$row['hit']=0;
		
		$smcFunc['db_insert']('replace','{db_prefix}tg_notify_antibf',
			array('chatID' => 'int', 'date' => 'int', 'hit' => 'int',),
			array($chatID, (int)date("Ymd"), ($row['hit']+1),),
			array('chatID')
		);
		tg_send($chatID, $txt['tg_bot_abf_wrong_key'].(5-$row['hit']));
		die();
	}
}

function tg_bot()
{
	global $txt, $smcFunc, $modSettings;
	if(empty($modSettings['tg_notify_enable']) || empty($modSettings['tg_notify_token']))
		return;
	
	loadLanguage('tg_notify');
	
	$json = file_get_contents('php://input');
	$obj = json_decode($json, TRUE);
	if(!$obj)die('0');
	
	$replyMarkup=NULL;
	
	//команда /start
	if(strpos($obj['message']['text'],'/start')===0){
		$msg=$txt['tg_bot_help'];
		tg_send($obj['message']['chat']['id'], $msg);
		die();
	}
	//команда /help
	elseif(strpos($obj['message']['text'],'/help')===0){
		$msg=$txt['tg_bot_help'];
		tg_send($obj['message']['chat']['id'], $msg);
		die();
	}
	//команда /key
	elseif(strpos($obj['message']['text'],'/key')===0){
		//антибрут
		tg_antibf($obj['message']['chat']['id']);
		
		$text=$obj['message']['text'];
		$text=(int)str_replace('/key', '', $text);
		
		//пустой или не цифра
		if($text==0){
			$msg=$txt['tg_bot_wrong_key'];
			tg_send($obj['message']['chat']['id'], $msg);
			die();
			
		}
		else{
			$text=$text^28579;
			if(strlen($text)>3 && substr($text,-3)=='193'){
				//сохраняем чатид в базе
				$memID=(int)str_replace('193','',$text);
				$smcFunc['db_insert']('replace','{db_prefix}telegram',
					array('memID' => 'int', 'chatID' => 'int',),
					array($memID, $obj['message']['chat']['id'],),
					array('chatID')
				);
				
				$msg=$txt['tg_bot_link_done'];
				tg_send($obj['message']['chat']['id'], $msg);
				die();
			}
			else{//Неправильный ключ.
				tg_antibf($obj['message']['chat']['id'],true);
				die();
			}
		}
		//на всякий случай :)
		die();
	}
	else{
		$msg=':)';
	}

	tg_send($obj['message']['chat']['id'], $msg);

	die();
}

function tg_link()
{
	global $context, $scripturl, $txt, $smcFunc, $modSettings;
	loadLanguage('tg_notify');
	
	if(empty($modSettings['tg_notify_enable']) || empty($modSettings['tg_notify_token']))
		fatal_lang_error('tg_notify_link_err',false);

	loadTemplate('tgnotify');
	

	$context['page_title'] = $txt['tg_link'];
	$context['page_title_html_safe'] = $smcFunc['htmlspecialchars'](un_htmlspecialchars($context['page_title']));

	$context['linktree'][] = array(
  		'url' => $scripturl. '?action=tg_link',
 		'name' => $txt['tg_link'],
	);
	
	
	//проверяем наличие телеграма у юзера
	$memID=isset($_REQUEST['u'])?(int)$_REQUEST['u']:$context['user']['id'];
	$request = $smcFunc['db_query']('', '
			SELECT memID
			FROM {db_prefix}telegram
			WHERE memID = {int:memID}
			LIMIT 1',
			array(
				'memID' => $memID,
			)
		);
	if($smcFunc['db_num_rows']($request) !== 1){
		$sec=$memID.'193'^28579;
		$context['tg_link_text'] = sprintf($txt['tg_notify_link'],$modSettings['tg_notify_botname'],$sec);
	}
	else{
		if(isset($_POST['del']) && ($context['user']['can_mod'] || $memID==$context['user']['id'])){
			$smcFunc['db_query']('','
				DELETE FROM {db_prefix}telegram
				WHERE memID ={int:memID}',
				array(
					'memID' => $memID,
				)
			);
			$context['tg_link_text'] = $txt['tg_notify_link2'];
			$smcFunc['db_free_result']($request);
			return;
		}	
		
		$context['tg_link_text'] = '
		<form action="'. $scripturl. '?action=tg_link" method="post"  class="flow_hidden">
			'.$txt['tg_notify_link1'].'
			<input type="hidden" name="del" value="qqq" />
			<input onclick="return confirm(\''.$txt['tg_notify_alert'].'\')" type="submit" value="'.$txt['tg_notify_button'].'" class="button_submit floatright" /><br>
		</form>	';
	}
	$smcFunc['db_free_result']($request);
}
?>