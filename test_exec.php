<?php
echo "<pre>";

echo "exec() test:\n";
var_dump(exec("ping 8.8.8.8 -n 1", $output));
print_r($output);

echo "\n\nshell_exec() test:\n";
var_dump(shell_exec("ping 8.8.8.8 -n 1"));

echo "\n\nproc_open test:\n";
$des = [
    0 => ["pipe", "r"],
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];
$p = @proc_open("ping 8.8.8.8 -n 1", $des, $pipes);
var_dump($p);
