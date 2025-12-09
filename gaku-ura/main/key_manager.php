<?php
require __DIR__ .'/../conf/conf.php';
require __DIR__ .'/../conf/users.php';
/*
 * ★主な機能★
 * 鍵の貸し借り状態を管理
 * 部室の施錠状態
 * 講義室等の予約状況
 * discodeに通知
 * 操作にはログイン必須(管理レベル3以上)、閲覧は無制限
*/
function main():int{
	$conf = new GakuUra();
	$user = new GakuUraUser($conf);
	$login_data = $user->login_check();
	$data_dir = $conf->data_dir.'/key_manager';

	/*
	 * データテーブルの設計
	 * 項目名: my_room my_room_else book_room book_room_else other
	 * 一行目: 0か1    コメント        部屋番      コメント          その他
	*/
	$my_room = ['0'=>'施錠', '1'=>'開錠'];
	$key_status_file = $data_dir.'/key_status.tsv';
	$key_status = [];
	if (is_file($key_status_file)){
		$key_status = explode("\t", get($key_status_file, 1));
	}
	for ($i = 0; $i < 5; ++$i){
		if (!isset($key_status[$i])){
			$key_status[$i] = [0, '', '', '', ''][$i];
		}
	}
	$config = read_conf($data_dir.'/key_manager.conf');
	$html_file = $data_dir.'/html/index.html';
	$css_file = $data_dir.'/css';
	$js_file = $data_dir.'/js';
	if (!is_file($html_file)){
		$conf->not_found();
	}
	$replace = [
		'MY_ROOM'=>$my_room[(string)$key_status[0]],
		'MY_ROOM_ELSE'=>$key_status[1],
		'BOOK_ROOM'=>$key_status[2],
		'BOOK_ROOM_ELSE'=>$key_status[3],
		'OTHER'=>$key_status[4],
		'SESSION_TOKEN'=>'',
		'LOGIN_NAME'=>'',
		'ERROR'=>''];

	/* フォーム送信 */
	if (list_isset($_POST,['submit','session_token','action','on_off','book_room_name','comment'])
	&& $conf->check_csrf_token('key_manager',$_POST['session_token'],true)
	&& $login_data['result']
	&& in_array($_POST['on_off'],['on','off','null'])
	&& in_array($_POST['action'],['blank','my_room_key','my_room_door','book_room','book_room_videokey','other','reset'],true)){
		$conf->file_lock('key_manager');
		if ($_POST['action']!=='other'&&$_POST['action']!=='reset' && $_POST['on_off']==='null'){
			$replace['ERROR'] = '<p class="error">内容が選択されていません。</p>';
		} else {
			$message = $login_data['user_data']['name'].'さんが';
			$date = date('[Y年m月d日H時i分] ');
			switch ($_POST['action']){
				case 'my_room_key':
				if ($_POST['on_off'] === 'on'){
					$key_status[1] = $date.'(鍵は借りた)';
					$message .= '鍵は借り';
				} else {
					$key_status[1] = $date.'(鍵は返した)';
					$message .= '鍵は返し';
				}
				break;

				case 'my_room_door':
				if ($_POST['on_off'] === 'on'){
					$key_status[0] = 1;
					$message .= $date.'部屋を開け';
				} else {
					$key_status[0] = 0;
					$message .= $date.'部屋を閉め';
				}
				break;

				case 'book_room':
				$room_name = str_replace(',', '', GakuUra::h(h($_POST['book_room_name'])));
				if (not_empty($room_name)){
					$key_status[2] = str_replace($room_name.', ', '', $key_status[2]);
					if ($_POST['on_off'] === 'on'){
						$key_status[2] .= $room_name.', ';
						$message .= $room_name.'を予約し';
					}
				} else {
					$replace['ERROR'] = '<p class="error">部屋名が入力されていません。</p>';
				}
				break;

				case 'book_room_videokey':
				$room_name = str_replace(',', '', GakuUra::h(h($_POST['book_room_name'])));
				if ($_POST['on_off'] === 'on'){
					$key_status[3] = $date.'(ビデオラックの鍵は借りた)';
					$message .= 'ビデオラックの鍵は借り';
				} else {
					$key_status[3] = $date.'(ビデオラックの鍵は返した)';
					$message .= 'ビデオラックの鍵は返し';
					if (not_empty($room_name)){
						$key_status[2] = str_replace($room_name.', ', '', $key_status[2]);
					}
				}
				break;

				case 'other':
				$key_status[4] = $date.nl2br(h($_POST['comment']), false);
				$message .= 'メッセージを送信し';
				break;

				case 'reset':
				if (is_file($key_status_file)){
					unlink($key_status_file);
					$key_status = [0,'','','',''];
				}
				$message .= '初期化し';
				break;

				case 'blank':
				$replace['ERROR'] = '<p class="error">操作を選択してください。</p>';
				break;
			}
		}
		if ($replace['ERROR'] === ''){
			for ($i = 0; $i < 5; ++$i){
				$key_status[$i] = GakuUraUser::h($key_status[$i]);
			}
			file_put_contents($key_status_file, row(implode("\t",$key_status))."\n", LOCK_EX);
			$conf->file_unlock('key_manager');

			//discodeで周知
			if (isset($config['discode_url']) && not_empty($config['discode_url'])){
				$message .= 'たですぅ。';
				if (not_empty($_POST['comment'])){
					$message .= "\n".'コメント「'.h($_POST['comment']).'」';
				}
				$discode_data = [
					'username'=>'あまさん',
					'content'=>$message,
					'avatar_url'=>(isset($config['discode_icon'])?$config['discode_icon']:'')];
				$discode_json = json_encode($discode_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
				$ch = curl_init($config['discode_url']);
				curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $discode_json);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$response = curl_exec($ch);
				curl_close($ch);
			}
			if ($_POST['submit'] !== 'javascript'){
				header('Location:./');
				exit;
			}
		} else {
			$conf->file_unlock('key_manager');
		}
	}
	if ($login_data['result'] && (int)$login_data['user_data']['admin']>=3){
		$replace['LOGIN_NAME'] = $login_data['user_data']['name'];
		$replace['SESSION_TOKEN'] = $conf->set_csrf_token('key_manager');
	}

	/* 非同期通信 */
	if ((isset($_POST['reload'])&&$_POST['reload']==='json') || (isset($_POST['submit'])&&$_POST['submit']==='javascript')){
		header('Content-Type:application/json;charset=UTF-8');
		echo json_encode($replace, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		return 0;
	}

	$html = file_get_contents($html_file);
	remove_comment_rows($html, '<!--', '-->');
	if (!$login_data['result'] || (int)$login_data['user_data']['admin']<3){
		remove_comment_rows($html, '<admin>', '</admin>');
	} else {
		$html = str_replace('<admin>', '', $html);
		$html = str_replace('</admin>', '', $html);
	}
	foreach ($replace as $k => $v){
		$html = str_replace('{'.$k.'}', $v, $html);
	}
	$conf->html('HAMKEY-', '鍵の状態', $html, $css_file, $js_file, true);
	return 0;
}
