#!/usr/bin/env php
<?php

const LISTEN_PORT = 61672;
const DOM_URL = "http://localhost:8080/json.htm?type=command&param=udevice&idx=%d&nvalue=0&svalue=%.1f;%d;0";

$dom_ids = [
	"int" => 162,
	"ext" => 163,
];

function decode_temp(int $w): float {
	$tmp = $w & 0x7ff;
	if ($tmp < 1024) {
		return $tmp / 10;
	} else {
		return (2048 - $tmp) / -10;
	}
}

function decode_hum(int $w): int {
	return (int)($w & 0x7f);
}

if (!$srv = stream_socket_server("tcp://0.0.0.0:" . LISTEN_PORT, $errno, $errstr)) {
	throw new Exception("cannot open listening socket : {$errstr}");
}

while ($c = stream_socket_accept($srv, -1)) {
	$fwd_hdrs = [];
	// read and decode http request headers
	do {
		$hdr = trim(fgets($c, 1024));
		if (preg_match('%^([A-Z]+) (http://[^ ]+) HTTP/%', $hdr, $res)) {
			$method = $res[1];
			$url = $res[2];
		} else if (preg_match('/^([^ ]+): (.+)$/', $hdr, $res)) {
			$fwd_hdrs[] = $hdr;
			if (strtolower($res[1]) === "content-length") {
				$clen = (int)$res[2];
			}
		}
	} while ($hdr);
	// read http request body
	$body = "";
	do {
		$body .= fread($c, $clen - strlen($body));
	} while (strlen($body) < $clen);
	// decode lacrosse binary protocol
	if ($body[0] === chr(0xda)) {
		list(,$temp_in_w, $hum_in_w, $temp_out_w, $hum_out_w) = unpack("n4", substr($body, 14));
		$temp_in = decode_temp($temp_in_w);
		$hum_in = decode_hum($hum_in_w);
		$temp_out = decode_temp($temp_out_w);
		$hum_out = decode_hum($hum_out_w);
	}
	// forward to domoticz
	file_get_contents(sprintf(DOM_URL, $dom_ids["int"], $temp_in, $hum_in));
	file_get_contents(sprintf(DOM_URL, $dom_ids["ext"], $temp_out, $hum_out));
	// forward to lacrosse server
	$fwd_resp = file_get_contents($url, false, stream_context_create([
		"http" => [
			"method" => $method,
			"header" => $fwd_hdrs,
			"content" => $body,
		],
	]));
	// forward response back to lacrosse gateway
	$resp  = "HTTP/1.1 200 OK\r\n";
	$resp .= "Server: lacrosse-domoticz-proxy/1.0\r\n";
	$resp .= "Content-Length: " . strlen($fwd_resp) . "\r\n";
	$resp .= "Content-Type: application/octet-stream\r\n";
	$resp .= "Connection: close\r\n";
	$resp .= "\r\n";
	$resp .= $fwd_resp;
	fwrite($c, $resp);
	fclose($c);
}
fclose($srv);