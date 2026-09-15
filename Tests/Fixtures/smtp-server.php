<?php
// Local-only SMTP fixture. Never relays or stores messages.
$mode = $argv[1];
$server = stream_socket_server('tcp://127.0.0.1:0', $number, $error);
if (!$server) exit(1);
echo stream_socket_get_name($server, false)."\n"; flush();
$client = stream_socket_accept($server, 10);
if (!$client) exit(2);
stream_set_timeout($client, 5);
fwrite($client, "220 fixture ESMTP\r\n");
$data = false;
while (($line = fgets($client)) !== false) {
    if ($data) {
        if (rtrim($line) !== '.') continue;
        if ($mode === 'disconnect') break;
        fwrite($client, match ($mode) {
            'capacity' => "552 5.2.2 mailbox quota exceeded\r\n",
            'reject_data' => "554 5.7.1 policy rejection\r\n",
            default => "250 2.0.0 accepted\r\n",
        });
        $data = false; continue;
    }
    $command = strtoupper(strtok(trim($line), ' '));
    if ($command === 'EHLO') fwrite($client, $mode === 'auth' ? "250-fixture\r\n250 AUTH PLAIN\r\n" : ($mode === 'tls' ? "250-fixture\r\n250 STARTTLS\r\n" : "250 fixture\r\n"));
    elseif ($command === 'STARTTLS') { fwrite($client, "220 begin TLS\r\n"); break; }
    elseif ($command === 'AUTH') fwrite($client, "535 5.7.8 invalid credentials\r\n");
    elseif ($command === 'RCPT') fwrite($client, $mode === 'recipient' ? "550 5.1.1 no such mailbox\r\n" : ($mode === 'temporary' ? "451 4.3.0 try later\r\n" : "250 recipient accepted\r\n"));
    elseif ($command === 'DATA') { fwrite($client, "354 end with dot\r\n"); $data = true; }
    elseif ($command === 'QUIT') { fwrite($client, "221 goodbye\r\n"); break; }
    else fwrite($client, "250 OK\r\n");
}
fclose($client); fclose($server);
