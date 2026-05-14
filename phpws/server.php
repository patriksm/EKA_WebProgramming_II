<?php
// Tell PHP not to time out — we want this script to run forever
set_time_limit(0);

// HOST and PORT where our WebSocket server will listen
define('HOST', '127.0.0.1');
define('PORT', 8080);

// Create a TCP/IP socket
// AF_INET  = IPv4, SOCK_STREAM = TCP (reliable, ordered), SOL_TCP = TCP protocol
$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// Allow reusing the port immediately after the server restarts
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);

// Bind the socket to our chosen host and port
socket_bind($server, HOST, PORT);

// Start listening — allow up to 20 pending connections in the queue
socket_listen($server, 20);

echo "✅ WebSocket server started on ws://" . HOST . ":" . PORT . "\n";
echo "   Waiting for connections...\n";

// $clients holds ALL open socket connections
// We start with just the server socket itself
$clients = [$server];

// $handshakeDone tracks which clients have completed the WebSocket handshake
$handshakeDone = [];

	/**
 * Called whenever a client socket has data ready to read.
 * Decides whether to do the handshake or handle a WS frame.
 */
	function handleClient($socket, &$clients, &$handshakeDone): void
	{
    // Read up to 4096 bytes from the client
    $data = socket_read($socket, 4096);

    if ($data === false || $data === '') {
        // Client disconnected
        removeClient($socket, $clients, $handshakeDone);
        return;
    }

    //$id = (int)$socket; // Use socket as a unique ID key
	$id = (int)$data;

    if (!isset($handshakeDone[$id])) {
        // First message from this client must be the HTTP upgrade request
        doHandshake($socket, $data, $handshakeDone);
    } else {
        // Already handshaked — process as a WebSocket data frame
        processFrame($socket, $data, $clients, $handshakeDone);
    }
	}

/**
 * Performs the RFC 6455 WebSocket opening handshake.
 */
function doHandshake($socket, string $request, &$handshakeDone): void
{
    // Extract the Sec-WebSocket-Key from the HTTP headers
    if (!preg_match('/Sec-WebSocket-Key: (.+)\r\n/', $request, $matches)) {
        echo "❌ Not a WebSocket request — closing.\n";
        socket_close($socket);
        return;
    }

    $key = trim($matches[1]);

    // The magic UUID defined in RFC 6455 — every WebSocket server uses this
    $magicString = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    // Compute the acceptance key:
    // 1. Concatenate the client key + magic string
    // 2. SHA-1 hash the result
    // 3. Base64-encode the hash
    $acceptKey = base64_encode(sha1($key . $magicString, true));

    // Build the HTTP 101 response — must include exactly these headers
    $response  = "HTTP/1.1 101 Switching Protocols\r\n";
    $response .= "Upgrade: websocket\r\n";
    $response .= "Connection: Upgrade\r\n";
    $response .= "Sec-WebSocket-Accept: {$acceptKey}\r\n";
    $response .= "\r\n"; // Empty line signals end of HTTP headers

    socket_write($socket, $response);
	
	$data = socket_read($socket, 4096);

    // Mark this socket as "handshake done"
    $handshakeDone[(int)$data] = true;
    echo "🤝 Handshake complete for socket " . (int)$socket . ".\n";
}

/**
 * Decodes a raw WebSocket frame sent by the browser.
 * Returns the decoded text payload, or false on error.
 */
function decodeFrame(string $data)
{
    // We need at least 6 bytes (2 header + 4 mask)
    if (strlen($data) < 6) return false;

    // Byte 1: FIN bit + opcode
    // opcode 0x1 = text frame, 0x8 = close frame
    // ord() converts a character to its numeric ASCII/byte value
    $opcode = ord($data[0]) & 0x0F;

    if ($opcode === 0x8) {
        return false; // Close frame — signal disconnect
    }

    // Byte 2: MASK flag (bit 7) + payload length (bits 0-6)
    // & 0x7F clears the top bit to get just the 7-bit length
    $length = ord($data[1]) & 0x7F;

    // For simplicity we handle only payloads up to 125 bytes
    // (Larger messages need 2 or 8 more length bytes — see RFC 6455 §5.2)
    if ($length > 125) return false;

    // The 4 masking-key bytes start at byte index 2
    $masks = substr($data, 2, 4);

    // The actual payload starts at byte index 6
    $encoded = substr($data, 6, $length);

    // XOR each payload byte with the corresponding mask byte (cycling through 4 masks)
    // This is the unmasking algorithm from RFC 6455
    $decoded = '';
    for ($i = 0; $i < strlen($encoded); $i++) {
        $decoded .= $encoded[$i] ^ $masks[$i % 4];
    }

    return $decoded;
}

/**
 * Encodes a text string into a WebSocket frame for sending.
 * Server-to-client frames are NOT masked (RFC 6455 §5.1).
 */
function encodeFrame(string $text): string
{
    $length = strlen($text);

    // Byte 1: 0x81 = FIN bit set (1) + opcode 0x1 (text frame)
    // chr() converts a number to the corresponding byte character
    $frame = chr(0x81);

    if ($length <= 125) {
        // Length fits in 7 bits — write it directly as byte 2
        $frame .= chr($length);
    } elseif ($length <= 65535) {
        // 126 signals "next 2 bytes hold the real length" (big-endian)
        $frame .= chr(126) . pack('n', $length);
    }
    // Append the raw text payload (no masking needed from server)
    $frame .= $text;

    return $frame;
}

/**
 * Called when a handshaked client sends a WebSocket frame.
 */
function processFrame($socket, string $data, &$clients, &$handshakeDone): void
{
    $message = decodeFrame($data);

    if ($message === false) {
        // Close frame or bad data — remove client
        removeClient($socket, $clients, $handshakeDone);
        return;
    }

    // Trim whitespace and ignore empty messages
    $message = trim($message);
    if ($message === '') return;

    echo "💬 Message received: {$message}\n";

    // Broadcast to all OTHER connected (and handshaked) clients
    broadcast($socket, $message, $clients, $handshakeDone);
}

/**
 * Sends a message to every client except the sender.
 */
function broadcast($sender, string $message, array $clients, array $handshakeDone): void
{
    $frame = encodeFrame($message);

    foreach ($clients as $client) {
        // Skip the server socket itself and the sender
        if (!isset($handshakeDone[(int)$client])) continue;
        if ($client === $sender) continue;

        socket_write($client, $frame);
    }
}

/**
 * Closes and removes a disconnected client from our tracking arrays.
 */
function removeClient($socket, &$clients, &$handshakeDone): void
{
    echo "🔌 Client disconnected: socket " . (int)$socket . "\n";

    socket_close($socket);

    // Remove from the clients list
    $key = array_search($socket, $clients);
    if ($key !== false) unset($clients[$key]);

    // Remove handshake record
    unset($handshakeDone[(int)$socket]);
}


// Main event loop — runs forever, processing events
while (true) {
    // Copy the $clients array — socket_select() modifies it
    $read = $clients;
    $write = null;
    $except = null;

    // socket_select() blocks here until at least one socket has activity
    // It puts only the "active" sockets back into $read
    if (socket_select($read, $write, $except, 0, 10000) === false) {
		echo "socket_select() failed: " . socket_strerror(socket_last_error()) . "\n";
        break;
    }

    // Loop over every socket that has data waiting
    foreach ($read as $socket) {
        if ($socket === $server) {
            // A NEW client is trying to connect
            $client = socket_accept($server);
            $clients[] = $client;
            echo "🔗 New connection accepted.\n";
        } else {
            // An EXISTING client sent data — we handle it next
            handleClient($socket, $clients, $handshakeDone);
        }
    }
	
}

socket_close($server);