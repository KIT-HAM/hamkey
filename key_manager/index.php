<?php
$f = __DIR__ .'/../gaku-ura/main/key_manager.php';
if (is_file($f)){
	require $f;
	main();
}
