<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Bot Emulator - Local Testing</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto p-6 max-w-4xl">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold text-gray-800 mb-2">🤖 Telegram Bot Emulator</h1>
            <p class="text-gray-600 mb-6">Test bot commands locally without webhook setup</p>

            <!-- Test Info -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-blue-800 mb-2">📋 Test Configuration:</h3>
                <ul class="text-blue-700 text-sm space-y-1">
                    <li><strong>Test User:</strong> @Salesmanp2p (ID: 5928450281)</li>
                    <li><strong>Bot:</strong> {{ $bot['username'] }}</li>
                    <li><strong>Test Video:</strong> ID 11 - "Science Video - Research Lab"</li>
                </ul>
            </div>

            <!-- Quick Test Buttons -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <button onclick="testCommand('/start')" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700 transition">
                    🚀 /start
                </button>
                <button onclick="testCommand('/help')" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                    ❓ /help
                </button>
                <button onclick="testCommand('/mypurchases')" class="bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700 transition">
                    📋 /mypurchases
                </button>
                <button onclick="testCommand('/getvideo 11')" class="bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700 transition">
                    🎬 /getvideo 11
                </button>
            </div>

            <!-- Custom Command Input -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Custom Command:</label>
                <div class="flex gap-2">
                    <input
                        type="text"
                        id="customCommand"
                        placeholder="/getvideo 11"
                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                    <button
                        onclick="testCustomCommand()"
                        class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700 transition"
                    >
                        Send
                    </button>
                </div>
            </div>

            <!-- Response Area -->
            <div class="bg-gray-50 border rounded-lg p-4 mb-6">
                <h3 class="font-semibold text-gray-800 mb-2">Response:</h3>
                <div id="response" class="text-gray-600 text-sm">
                    Click a button above to test bot commands...
                </div>
            </div>

            <!-- System Status -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 class="font-semibold text-yellow-800 mb-2">📊 System Status</h3>
                <div id="systemStatus" class="text-yellow-700 text-sm">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <script>
        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        // Test a specific command
        async function testCommand(command) {
            showLoading();

            try {
                const response = await fetch('/telegram/bot-emulator', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ command: command })
                });

                const data = await response.json();

                if (data.success) {
                    showResponse(data);
                } else {
                    showError('Command failed: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                showError('Network error: ' + error.message);
            }
        }

        // Test custom command
        function testCustomCommand() {
            const command = document.getElementById('customCommand').value.trim();
            if (command) {
                testCommand(command);
                document.getElementById('customCommand').value = '';
            }
        }

        // Show loading state
        function showLoading() {
            document.getElementById('response').innerHTML = '<div class="text-blue-600">⏳ Processing command...</div>';
        }

        // Show successful response
        function showResponse(data) {
            const html = `
                <div class="space-y-2">
                    <div class="text-green-600 font-semibold">✅ Command sent: ${data.command}</div>
                    <div class="text-gray-700">${data.message}</div>
                    <div class="text-xs text-gray-500 mt-2">
                        Check your Telegram ({{ $bot['username'] }}) for the actual bot response.
                    </div>
                </div>
            `;
            document.getElementById('response').innerHTML = html;
        }

        // Show error
        function showError(message) {
            document.getElementById('response').innerHTML = `<div class="text-red-600">❌ ${message}</div>`;
        }

        // Load system status
        async function loadSystemStatus() {
            try {
                const response = await fetch('/system-status');
                const data = await response.json();

                const status = data.system_ready ?
                    '<span class="text-green-600">✅ Ready</span>' :
                    '<span class="text-red-600">❌ Not Ready</span>';

                const html = `
                    <div class="space-y-1">
                        <div><strong>System:</strong> ${status}</div>
                        <div><strong>Test Video:</strong> ${data.test_video ? data.test_video.title + ' (ID: ' + data.test_video.id + ')' : 'Not found'}</div>
                        <div><strong>File ID Available:</strong> ${data.test_video?.has_file_id ? '✅ Yes' : '❌ No'}</div>
                        <div><strong>Recent Purchases:</strong> ${data.recent_purchases}</div>
                        <div><strong>Telegram Users:</strong> ${data.telegram_users}</div>
                    </div>
                `;

                document.getElementById('systemStatus').innerHTML = html;
            } catch (error) {
                document.getElementById('systemStatus').innerHTML = '<span class="text-red-600">❌ Failed to load status</span>';
            }
        }

        // Allow Enter key in custom command
        document.getElementById('customCommand').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                testCustomCommand();
            }
        });

        // Load status on page load
        loadSystemStatus();
    </script>
</body>

</html>
