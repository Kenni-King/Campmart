/* CampMart AI support chatbot floating widget.
 * Requires: material-symbols-outlined (loaded by footer/header pages).
 */
(function () {
    'use strict';

    var BASE = '';
    var baseEl = document.querySelector('base');
    if (baseEl) BASE = baseEl.getAttribute('href') || '';

    var history = [];
    var busy = false;

    function createWidget() {
        var holder = document.createElement('div');
        holder.id = 'aiChatbot';
        holder.className = 'ai-chatbot';
        holder.style.cssText =
            'position:fixed;bottom:88px;right:20px;z-index:9999;display:flex;flex-direction:column;' +
            'width:360px;max-width:calc(100vw - 32px);max-height:min(560px, calc(100vh - 140px));' +
            'background:#fff;border:1px solid #e2e8f0;border-radius:16px;box-shadow:0 20px 60px rgba(2,6,23,.25);' +
            'overflow:hidden;transform:translateY(16px);opacity:0;pointer-events:none;transition:all .2s ease;';

        holder.innerHTML =
            '<div style="display:flex;align-items:center;gap:10px;padding:14px 16px;background:#064E3B;color:#fff;">' +
            '  <span class="material-symbols-outlined" style="font-size:22px;">support_agent</span>' +
            '  <div style="flex:1;">' +
            '    <div style="font-weight:800;font-size:14px;">CampMart Assistant</div>' +
            '    <div style="font-size:11px;opacity:.8;">AI support bot &middot; replies instantly</div>' +
            '  </div>' +
            '  <button id="aiChatbotClose" style="background:none;border:none;color:#fff;cursor:pointer;font-size:20px;">' +
            '    <span class="material-symbols-outlined">close</span></button>' +
            '</div>' +
            '<div id="aiChatbotMessages" style="flex:1;overflow-y:auto;padding:14px;background:#F9FAFB;font-size:13px;"></div>' +
            '<div style="display:flex;gap:8px;padding:12px;border-top:1px solid #e2e8f0;background:#fff;">' +
            '  <textarea id="aiChatbotInput" rows="1" placeholder="Ask me anything about CampMart..." style="flex:1;resize:none;border:1px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-size:13px;outline:none;max-height:80px;"></textarea>' +
            '  <button id="aiChatbotSend" style="background:#f48c25;color:#fff;border:none;border-radius:10px;padding:0 14px;cursor:pointer;font-weight:700;">' +
            '    <span class="material-symbols-outlined" style="font-size:20px;vertical-align:middle;">send</span></button>' +
            '</div>';

        document.body.appendChild(holder);

        var messages = holder.querySelector('#aiChatbotMessages');
        var input = holder.querySelector('#aiChatbotInput');
        var sendBtn = holder.querySelector('#aiChatbotSend');

        function addBubble(role, text) {
            var wrap = document.createElement('div');
            wrap.style.cssText = 'display:flex;margin-bottom:10px;' + (role === 'user' ? 'justify-content:flex-end;' : '');
            var bubble = document.createElement('div');
            bubble.style.cssText =
                'max-width:82%;padding:9px 12px;border-radius:12px;white-space:pre-wrap;line-height:1.45;' +
                (role === 'user'
                    ? 'background:#f48c25;color:#fff;border-bottom-right-radius:3px;'
                    : 'background:#fff;border:1px solid #e2e8f0;color:#1F2937;border-bottom-left-radius:3px;');
            bubble.textContent = text;
            wrap.appendChild(bubble);
            messages.appendChild(wrap);
            messages.scrollTop = messages.scrollHeight;
        }

        function showTyping() {
            var wrap = document.createElement('div');
            wrap.id = 'aiChatbotTyping';
            wrap.style.cssText = 'display:flex;margin-bottom:10px;';
            var bubble = document.createElement('div');
            bubble.style.cssText = 'background:#fff;border:1px solid #e2e8f0;padding:9px 14px;border-radius:12px;color:#94a3b8;';
            bubble.textContent = 'Thinking...';
            wrap.appendChild(bubble);
            messages.appendChild(wrap);
            messages.scrollTop = messages.scrollHeight;
        }

        function removeTyping() {
            var t = messages.querySelector('#aiChatbotTyping');
            if (t) t.remove();
        }

        function send() {
            var text = input.value.trim();
            if (!text || busy) return;
            busy = true;
            addBubble('user', text);
            history.push({ role: 'user', content: text });
            input.value = '';
            input.style.height = 'auto';
            showTyping();

            fetch(BASE + 'api/ai/chatbot.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ messages: history })
            })
                .then(function (res) { return res.json(); })
                .then(function (data) {
                    removeTyping();
                    busy = false;
                    var reply = data && data.success ? data.reply : (data && data.message) || 'Sorry, I could not reach the assistant right now. Please try again later or create a support ticket.';
                    addBubble('assistant', reply);
                    history.push({ role: 'assistant', content: reply });
                })
                .catch(function () {
                    removeTyping();
                    busy = false;
                    addBubble('assistant', 'Sorry, something went wrong. Please try again later or create a support ticket.');
                });
        }

        sendBtn.addEventListener('click', send);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                send();
            }
        });
        input.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 80) + 'px';
        });

        holder.querySelector('#aiChatbotClose').addEventListener('click', function () {
            holder.style.opacity = '0';
            holder.style.pointerEvents = 'none';
            launcher.style.display = 'flex';
        });

        if (!history.length) {
            var welcome = 'Hi! I\u2019m the CampMart assistant. I can help with buying, selling, verification, safety, payments, lost & found and more. How can I help?';
            history.push({ role: 'assistant', content: welcome });
            addBubble('assistant', welcome);
        }
    }

    var launcher = document.createElement('button');
    launcher.id = 'aiChatbotLauncher';
    launcher.style.cssText =
        'position:fixed;bottom:88px;right:20px;z-index:9998;width:56px;height:56px;border-radius:50%;' +
        'background:#064E3B;color:#fff;border:none;cursor:pointer;box-shadow:0 8px 24px rgba(6,78,59,.4);' +
        'display:flex;align-items:center;justify-content:center;';
    launcher.innerHTML = '<span class="material-symbols-outlined" style="font-size:26px;">chat</span>';
    launcher.setAttribute('aria-label', 'Open CampMart AI assistant');
    launcher.addEventListener('click', function () {
        launcher.style.display = 'none';
        if (!document.getElementById('aiChatbot')) createWidget();
        var w = document.getElementById('aiChatbot');
        w.style.opacity = '1';
        w.style.pointerEvents = 'auto';
        w.style.transform = 'translateY(0)';
        var input = w.querySelector('#aiChatbotInput');
        if (input) input.focus();
    });

    document.body.appendChild(launcher);
})();
