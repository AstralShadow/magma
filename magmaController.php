<?php
	$start = time() + microtime();
	echo "\n";
	if(isset($argv[1]))
	switch($argv[1]){
		case "users":{
			echo "Users:\n";
			if(file_exists("users.dat") && filesize("users.dat") > 0){
				$users = fopen("users.dat", "r");
				while(ftell($users) < filesize("users.dat")){
					$ip = unpack('C', fread($users, 1))[1];
					$ip .= ".".unpack('C', fread($users, 1))[1];
					$ip .= ".".unpack('C', fread($users, 1))[1];
					$ip .= ".".unpack('C', fread($users, 1))[1];
					$username = fread($users, unpack('C', fread($users, 1))[1]);
					$salt = fread($users, unpack('C', fread($users, 1))[1]);
					$folder = fread($users, unpack('C', fread($users, 1))[1]);
					echo $ip." '".$username."' ".$folder."\n";
				}
			}else echo "There is nothing in users.dat";
		}break;
		case "mkuser":{
			do{ $name;
				echo "Username: ";
				$name = trim(fgets(STDIN));
				if(strlen($name) < 3)
					echo "At least 3 characters.\n";
			}while(strlen($name) < 3);
			do{ $pass;
				echo "Password: ";
				$pass = trim(fgets(STDIN));
				if(strlen($pass) < 8)
					echo "At least 8 characters.\n";
			}while(strlen($pass) < 8);
			do{ $dir;
				echo "Directory: ";
				$dir = trim(fgets(STDIN));
				if(!preg_match("#^([a-zA-Z0-9]+(?:[/]))+$#", $dir))
					echo "Write something like: dir/ or dir/dir/ \n";
			}while(!preg_match("#^([a-zA-Z0-9]+(?:[/]))+$#", $dir));
			do{ $ip;
				echo "IP: ";
				$ip = trim(fgets(STDIN));
				if(!preg_match("#^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)){3}$#", $ip)){
					if($ip == "" || $ip == "*")
						$ip = "0.0.0.0";
					else
						echo "Write something like: 1.2.3.4 or 123.234.255.16\n";
				}
			}while(!preg_match("#^(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)(\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)){3}$#", $ip));
			$ip = explode('.', $ip);
			$pass = crypt($pass);
			// We dont wanna count the user input time, right?
			$start = time() + microtime();
			
			if(file_exists("users.dat"))
				$file = fopen("users.dat", "a");
			else $file = fopen("users.dat", "c");
			fwrite($file, pack('C', $ip[0]));
			fwrite($file, pack('C', $ip[1]));
			fwrite($file, pack('C', $ip[2]));
			fwrite($file, pack('C', $ip[3]));
			fwrite($file, pack('C', strlen($name)).$name);
			fwrite($file, pack('C', strlen($pass)).$pass);
			fwrite($file, pack('C', strlen($dir)).$dir);
		}break;
		case "rmuser":{
			if(!isset($argv[2])){
				echo "Username: ";
				$name = trim(fgets(STDIN));
			}else $name = trim($argv[2]);
			
			$found = false;
			
			if(file_exists("users.dat") && filesize("users.dat") > 0){
				$users = fopen("users.dat", "r");
				$new = fopen("users.temp", "w");
				while(ftell($users) < filesize("users.dat")){
					$pointer = ftell($users);
					fread($users, 4);
					$username = fread($users, unpack('C', fread($users, 1))[1]);
					fread($users, unpack('C', fread($users, 1))[1]);
					fread($users, unpack('C', fread($users, 1))[1]);
					if($name != $username){
						$newPointer = ftell($users);
						fseek($users, $pointer);
						fwrite($new, fread($users, $newPointer - $pointer));
					}else $found = true;
				}
				fclose($users);
				fclose($new);
				rename("users.temp", "users.dat");
			}
			if($found) echo "User [".$name."] removed.";
			else echo "No such user found.";
		}break;
		case 'stop':{
			if(file_exists("working.f"))
				unlink("working.f");
		}break;
		case 'DeactivateLimiters':{
			if(!file_exists("maxResource.f")){
				$a = fopen("maxResource.f", "c");
				fclose($a);
			}
		}break;
		case 'ActivateLimiters':{
			if(file_exists("maxResource.f"))
				unlink("maxResource.f");
		}break;
		default:
			echo "Unknown command [".$argv[1]."].\n\n";
		case "list":
			echo " Avaliable commands:\n";
			echo "list\n";
			echo "users\n";
			echo "mkuser\n";
			echo "rmuser [name]\n";
			echo "run\n";
			echo "stop\n";
			echo "DeactivateLimiters\n";
			echo "ActivateLimiters\n"; // This one is called on running.
			break;
	}
	echo "\n[".round((time() + microtime() - $start) * 1000)."ms]\n";