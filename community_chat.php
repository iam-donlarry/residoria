<?php
// community_chat.php
require_once 'config.php';

// Mocking session for prototype if not set
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Default Admin
}

$user_id = $_SESSION['user_id'];

// Handle POST request (New Message)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_message'])) {
    $estate_id = get_estate_id();
    $message = $conn->real_escape_string($_POST['message']);
    if (!empty($message)) {
        $conn->query("INSERT INTO community_chat (estate_id, user_id, message) VALUES ($estate_id, '$user_id', '$message')");
    }
    // Return for AJAX? Or just simple redirect for now
    if (isset($_POST['ajax'])) {
        exit('success');
    }
}

// Fetch user info for header
$user_res = $conn->query("SELECT name FROM users WHERE id = $user_id");
$current_user = $user_res->fetch_assoc();

// If AJAX fetch messages
if (isset($_GET['fetch_messages'])) {
    $estate_id = get_estate_id();
    $messages_res = $conn->query("SELECT c.*, u.name as sender_name 
                                 FROM community_chat c 
                                 JOIN users u ON c.user_id = u.id 
                                 WHERE c.estate_id = $estate_id
                                 ORDER BY c.created_at ASC 
                                 LIMIT 100");
    $messages = [];
    while ($m = $messages_res->fetch_assoc()) {
        $m['is_me'] = ($m['user_id'] == $user_id);
        $timestamp = strtotime($m['created_at']);
        if (date('Y-m-d', $timestamp) == date('Y-m-d')) {
            $m['time'] = date('H:i', $timestamp);
        } else {
            $m['time'] = date('M j, H:i', $timestamp);
        }
        $messages[] = $m;
    }
    header('Content-Type: application/json');
    echo json_encode($messages);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Chat - EstateAdmin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f1f5f9;
            margin: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        .chat-header {
            background: #ffffff;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            z-index: 10;
        }
        .chat-header h1 {
            font-family: 'Outfit', sans-serif;
            font-size: 1.25rem;
            margin: 0;
            color: #1e293b;
        }
        .chat-container {
            flex: 1;
            overflow-y: auto;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .message-bubble {
            max-width: 70%;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            position: relative;
            line-height: 1.5;
            font-size: 0.95rem;
        }
        .message-me {
            background: #3b82f6;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 0.25rem;
        }
        .message-other {
            background: white;
            color: #1e293b;
            align-self: flex-start;
            border-bottom-left-radius: 0.25rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .sender-name {
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #64748b;
        }
        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.25rem;
            text-align: right;
        }
        .chat-input-area {
            background: white;
            padding: 1.5rem 2rem;
            border-top: 1px solid #e2e8f0;
        }
        .input-container {
            display: flex;
            gap: 1rem;
            max-width: 1000px;
            margin: 0 auto;
        }
        input[type="text"] {
            flex: 1;
            padding: 0.75rem 1.25rem;
            border: 1px solid #cbd5e1;
            border-radius: 9999px;
            outline: none;
            font-family: inherit;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        input[type="text"]:focus {
            border-color: #3b82f6;
        }
        .btn-send {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 0 1.5rem;
            border-radius: 9999px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-send:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <div class="chat-header">
        <h1>Residents Community Chat</h1>
        <div style="font-size: 0.9rem; color: #64748b;">Logged in as: <strong><?= htmlspecialchars($current_user['name']) ?></strong></div>
    </div>

    <div class="chat-container" id="chatContainer">
        <!-- Messages will be loaded here via JS -->
    </div>

    <div class="chat-input-area">
        <form id="chatForm" class="input-container">
            <input type="text" id="messageInput" placeholder="Type a message..." required autocomplete="off">
            <button type="submit" class="btn-send">Send</button>
        </form>
    </div>

    <script>
        const chatContainer = document.getElementById('chatContainer');
        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('messageInput');

        async function fetchMessages() {
            const res = await fetch('community_chat.php?fetch_messages=1');
            const data = await res.json();
            
            chatContainer.innerHTML = '';
            data.forEach(msg => {
                const div = document.createElement('div');
                div.className = `message-bubble ${msg.is_me ? 'message-me' : 'message-other'}`;
                
                let html = '';
                if (!msg.is_me) {
                    html += `<div class="sender-name">${msg.sender_name}</div>`;
                }
                html += `<div>${msg.message}</div>`;
                html += `<div class="message-time">${msg.time}</div>`;
                
                div.innerHTML = html;
                chatContainer.appendChild(div);
            });
            chatContainer.scrollTop = chatContainer.scrollHeight;
        }

        chatForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = messageInput.value.trim();
            if (!msg) return;

            const formData = new FormData();
            formData.append('send_message', '1');
            formData.append('message', msg);
            formData.append('ajax', '1');

            messageInput.value = '';
            
            await fetch('community_chat.php', {
                method: 'POST',
                body: formData
            });
            
            fetchMessages();
        });

        // Initial fetch and poll every 3 seconds
        fetchMessages();
        setInterval(fetchMessages, 3000);
    </script>
</body>
</html>
