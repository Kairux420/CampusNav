<?php
require_once 'includes/auth_check.php';
require_once 'includes/header.php';
?>

<section class="chat-page">
    <div class="chat-shell">
        <div class="chat-header">
            <h1>AI Assistant</h1>
            <p>Ask for directions or general building information.</p>
        </div>

        <div class="chat-window" id="chatWindow">
            <div class="chat-message assistant">
                <strong>CampusNav AI</strong>
                <p>Hello! I am CampusNav AI. I know this building inside and out.<br>Try asking: <em>"Where is the nearest surau?"</em>, <em>"Take me to MK-204"</em>, or <em>"How do I find the computer labs?"</em></p>
            </div>
        </div>

        <form class="chat-form" id="chatForm">
            <input type="text" id="userMessage" name="message" placeholder="Type your question..." autocomplete="off" required>
            <button type="submit">Send</button>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('chatForm');
    const input = document.getElementById('userMessage');
    const chatWindow = document.getElementById('chatWindow');

    function addMessage(text, role) {
        const message = document.createElement('div');
        message.className = 'chat-message ' + role;
        const label = role === 'user' ? 'You' : 'CampusNav AI';
        message.innerHTML = '<strong>' + label + '</strong><p>' + text.replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/\n/g, '<br>') + '</p>';
        chatWindow.appendChild(message);
        chatWindow.scrollTop = chatWindow.scrollHeight;
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const message = input.value.trim();
        if (!message) {
            return;
        }

        addMessage(message, 'user');
        input.value = '';

        const loading = document.createElement('div');
        loading.className = 'chat-message assistant loading';
        loading.innerHTML = '<strong>CampusNav AI</strong><p>Thinking…</p>';
        chatWindow.appendChild(loading);
        chatWindow.scrollTop = chatWindow.scrollHeight;

        fetch('chat_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: 'message=' + encodeURIComponent(message)
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                chatWindow.removeChild(loading);

                if (data.success && data.redirect) {
                    addMessage('Opening navigation details...', 'assistant');
                    window.location.href = data.redirect;
                } else if (data.success && data.reply) {
                    addMessage(data.reply, 'assistant');
                } else {
                    addMessage(data.message || 'The assistant could not answer that request.', 'assistant');
                }
            })
            .catch(function () {
                chatWindow.removeChild(loading);
                addMessage('The assistant could not be reached right now.', 'assistant');
            });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
