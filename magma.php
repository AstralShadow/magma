<?php
	$address = "127.0.0.1";
	$port = 4181;
	
	// pcntl_signal may be implemented so no data is lost.
	// ..Once I rewrite this on linux.
	
	$baseConnection = socket_create(AF_INET, SOCK_STREAM, 0);
	socket_set_option($baseConnection, SOL_SOCKET, SO_REUSEADDR, 1);
	socket_bind($baseConnection, $address, $port);
	socket_set_nonblock($baseConnection);
	socket_listen($baseConnection);
	
	if($baseConnection){
		echo "\nMagma v0.0.5\n".$address.":".$port."\n";
		echo "System Time: ".date('Y-m-d H:i:s')."\n";
	}
	unset($address, $port);
	
	include("magmaUser.php");
	
	$lastActionTime = microtime() + time();
	
	$socketConnections = array($baseConnection);
	$socketUsers = array();
	$null = array(NULL, NULL); // handler
	if(!file_exists("working.f") || filemtime("working.f") + 5 < time()){
		$a = fopen("working.f", "c");
		fclose($a);
	}else exit;
	if(file_exists("maxResource.f"))
		unlink("maxResource.f");
	$speedLimited = true;
	
	while(file_exists("working.f")){
		touch("working.f");
		$newSocketArray = $socketConnections;
		socket_select($newSocketArray, $null[0], $null[1], 0, 10);
		if(count($newSocketArray) > 0)
			$lastActionTime = microtime() + time();
		
		if(array_search($baseConnection, $newSocketArray) !== false){
			unset($newSocketArray[array_search($baseConnection, $newSocketArray)]);
			if($b = socket_accept($baseConnection)){
				$socketConnections[] = $b;
				$id = array_search($b, $socketConnections);
				$socketUsers[$id] = new magmaUser($b, $id);
				unset($b, $id);
			}
		}
		
		foreach($newSocketArray as $socket){
			$msg = @socket_read($socket, 1);
			if($msg === false){
				$id = array_search($socket, $socketConnections);
				$socketUsers[$id]->onDisconnect();
				unset($socketUsers[$id]);
				unset($socketConnections[$id]);
				socket_close($socket);
			}else if($msg != ""){
				$action = unpack('C', $msg)[1] & 31;
				
				if($action != 0 && $socketUsers[array_search($socket, $socketConnections)]->active){
					$location = array();
					$id = null;
					$variable = null;
					$value = null;
					
					$locCount = unpack('C', @socket_read($socket, 1))[1];
					for($i = 0; $i < $locCount; $i++){
						$len = unpack('C', @socket_read($socket, 1))[1];
						$location[] = @socket_read($socket, $len);
					}
					unset($locCount, $i, $len);
					
					if(unpack('C', $msg)[1] & 32){
						$id = unpack('N', @socket_read($socket, 4))[1];
					};
					
					if(unpack('C', $msg)[1] & 64){
						$h = unpack('N', @socket_read($socket, 4))[1];
						$variable = @socket_read($socket, $h);
						unset($h);
					}
					
					if(unpack('C', $msg)[1] & 128){
						$h = unpack('N', @socket_read($socket, 4))[1];
						$value = @socket_read($socket, $h);
						unset($h);
					}
					
					$respond = $socketUsers[array_search($socket, $socketConnections)]
								->onMessage($action, $location, $id, $variable, $value);
					unset($location, $id, $variable, $value);
					
				}else{
					$respond = "418";
					$fine = true;
					$q = socket_read($socket, 4);
					if(strlen($q) != 4) $fine = false;
					if($fine){
						$h = min(256, unpack('N', $q)[1]);
						$username = socket_read($socket, $h);
					}
					if($fine)
						$q = socket_read($socket, 4);
					if($fine && strlen($q) != 4) $fine = false;
					if($fine){
						$h = min(256, unpack('N', $q)[1]);
						$password = socket_read($socket, $h);
					}
					
					unset($h, $q);
					
					if($fine)
						$respond = $socketUsers[array_search($socket, $socketConnections)]
								->login($username, $password);
					unset($username, $password, $fine);
				}
				
				// the responds are encoded in the user class
				socket_write($socket, $respond);
				unset($action, $respond);
			}else if($msg == ""){
				@socket_write($socket, " ");
			}
			if(!file_exists("maxResource.f"))
				usleep(75);
		}
		if(!file_exists("maxResource.f"))
			usleep(75);
		if($lastActionTime + 1 < time() + microtime())
			usleep(25000);
		else if($lastActionTime + 0.005 < time() + microtime()){
			unset($msg, $socket);
			usleep(5000);
		}
		if($speedLimited && file_exists("maxResource.f")){
			$speedLimited = false;
			echo "Limiters: Deactivated\n";
		}else if(!$speedLimited && !file_exists("maxResource.f")){
			$speedLimited = true;
			echo "Limiters: Activated\n";
		}
	}