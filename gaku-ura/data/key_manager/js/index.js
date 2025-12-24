/* 鍵の状態自動更新 */

#!include element.js;
#!include popup.js;

const popup = new POPUP();

async function reload_status(){
	const my_room = $ID("my_room");
	const my_room_else = $ID("my_room_else");
	const book_room = $ID("book_room");
	const book_room_else = $ID("book_room_else");
	const other = $ID("other");
	let p = await new URLSearchParams({"reload":"json"});
	try{
		const respond = await fetch("./",{
			method:"POST",
			headers:{"content-type": "application/x-www-form-urlencoded"},
			body:p.toString()
		});
		if (respond.ok){
			const r = await respond.json();
			if ($ID("form")){
				$QS('input[name="session_token"]').value = r["SESSION_TOKEN"];
			}
			my_room.innerHTML = r["MY_ROOM"];
			my_room_else.innerHTML = r["MY_ROOM_ELSE"];
			book_room.innerHTML = r["BOOK_ROOM"];
			book_room_else.innerHTML = r["BOOK_ROOM_ELSE"];
			other.innerHTML = r["OTHER"];
			if (!r["LOGIN_NAME"] && $ID("form")){
				$ID("form").remove();
			}
		}
	} catch {}
}
function prevent_enterkey(e){
	if ($QS("form input:focus") && (e.key==="Enter"||e.key==="Return")){
		e.preventDefault();
	}
}
async function decide_change(e){
	e.preventDefault();
	if (await popup.confirm("操作を確定しますか？<br><b>操作内容は部員へ通知されます。</b>")){
		const select_action = $QS('select[name="action"]');
		let p = await new URLSearchParams({
			"action":select_action.options[select_action.selectedIndex].value,
			"on_off":$QS('input[name="on_off"]:checked').value,
			"book_room_name":$QS('input[name="book_room_name"]').value,
			"comment":$QS('textarea[name="comment"]').value,
			"submit":"javascript",
			"session_token":$QS('input[name="session_token"]').value
		});
		try{
			const respond = await fetch("./",{
					method:"POST",
					headers:{"content-type": "application/x-www-form-urlencoded"},
					body:p.toString()
				}
			);
			if (respond.ok){
				const r = await respond.json();
				if (r["ERROR"]){
					popup.alert(r["ERROR"]);
				} else {
					$QS('input[type="text"]').value = "";
					$QS('textarea').value = "";
				}
				reload_status();
			}
		} catch {}
	}
}

setInterval(reload_status, 3000);

//権限がある場合のみ
if ($ID("form")){
	/* エンターキー誤送信の防止 */
	document.addEventListener("keydown", prevent_enterkey);
	/* リロードせずに投稿 */
	$ID("form").addEventListener("submit", decide_change);
}


