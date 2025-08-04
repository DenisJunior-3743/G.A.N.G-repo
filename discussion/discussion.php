<?php
session_start();

// Fallbacks for demo/testing
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['second_name'] = 'John';
    $_SESSION['picture'] = '/uploads/users/default.jpg'; // Replace with actual image path
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Discussion - G.A.N.G</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    :root {
      --primary-color: #007bff;
      --secondary-color: #6c757d;
      --success-color: #28a745;
      --danger-color: #dc3545;
      --light-bg: #f8f9fa;
      --dark-bg: #343a40;
      --message-bg-current: linear-gradient(135deg, #007bff, #0056b3);
      --message-bg-other: #ffffff;
      --border-radius: 18px;
      --shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .container {
      max-width: 1200px;
    }

    .chat-container {
      background: white;
      border-radius: 20px;
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .chat-header {
      background: var(--message-bg-current);
      color: white;
      padding: 1rem 1.5rem;
      border-radius: 20px 20px 0 0;
    }

    #messages {
      height: 500px;
      overflow-y: auto;
      padding: 1rem;
      background: #f8f9fa;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .message-bubble {
      display: flex;
      flex-direction: column;
      max-width: 75%;
      animation: slideIn 0.3s ease-out;
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .message-current {
      align-self: flex-end;
    }

    .message-other {
      align-self: flex-start;
    }

    .message-content {
      background: var(--message-bg-other);
      padding: 12px 16px;
      border-radius: var(--border-radius);
      box-shadow: var(--shadow);
      border: 1px solid #e9ecef;
      position: relative;
    }

    .message-current .message-content {
      background: var(--message-bg-current);
      color: white;
      border: none;
    }

    .user-info {
      display: flex;
      align-items: center;
      margin-bottom: 8px;
      font-size: 0.85rem;
      color: #6c757d;
    }

    .message-current .user-info {
      color: rgba(255,255,255,0.9);
      justify-content: flex-end;
    }

    .user-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      border: 2px solid #fff;
      margin-right: 8px;
      object-fit: cover;
    }

    .message-current .user-avatar {
      margin-right: 0;
      margin-left: 8px;
      order: 2;
    }

    .default-avatar {
      background: linear-gradient(135deg, #667eea, #764ba2);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: bold;
      font-size: 14px;
    }

    .message-actions {
      display: flex;
      gap: 5px;
      margin-top: 8px;
      opacity: 0;
      transition: opacity 0.2s;
    }

    .message-bubble:hover .message-actions {
      opacity: 1;
    }

    .message-current .message-actions {
      justify-content: flex-end;
    }

    .reply-indicator {
      background: rgba(0,0,0,0.05);
      border-left: 3px solid var(--primary-color);
      padding: 8px 12px;
      margin-bottom: 8px;
      border-radius: 0 8px 8px 0;
      font-size: 0.85rem;
      color: #6c757d;
    }

    .message-current .reply-indicator {
      background: rgba(255,255,255,0.1);
      border-left-color: rgba(255,255,255,0.5);
      color: rgba(255,255,255,0.8);
    }

    .input-section {
      padding: 1rem 1.5rem;
      background: white;
      border-top: 1px solid #e9ecef;
    }

    .reply-preview {
      background: #e3f2fd;
      border-left: 3px solid var(--primary-color);
      padding: 8px 12px;
      margin-bottom: 10px;
      border-radius: 0 8px 8px 0;
      display: none;
      position: relative;
    }

    .reply-preview .close-reply {
      position: absolute;
      right: 8px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: #666;
      cursor: pointer;
    }

    .recording-controls {
      display: none;
      align-items: center;
      gap: 10px;
      padding: 10px;
      background: #fff3cd;
      border: 1px solid #ffeaa7;
      border-radius: 8px;
      margin-bottom: 10px;
    }

    .recording-timer {
      font-weight: bold;
      color: var(--danger-color);
      min-width: 60px;
    }

    .audio-controls {
      width: 100%;
      margin-top: 5px;
    }

    .discussion-item {
      cursor: pointer;
      transition: all 0.2s;
      border-radius: 8px !important;
      margin-bottom: 5px;
    }

    .discussion-item:hover {
      background-color: #f8f9fa !important;
      transform: translateX(5px);
    }

    .discussion-item.active {
      background: var(--message-bg-current) !important;
      color: white !important;
    }

    .btn-record {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.2s;
    }

    .btn-record.recording {
      background: var(--danger-color) !important;
      border-color: var(--danger-color) !important;
      animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
      0% { transform: scale(1); }
      50% { transform: scale(1.1); }
      100% { transform: scale(1); }
    }

    .waveform-container {
      margin-left: 10px;
      border-radius: 4px;
      overflow: hidden;
    }

    /* Mobile Responsiveness */
    @media (max-width: 768px) {
      .container {
        padding: 0.5rem;
      }
      
      .message-bubble {
        max-width: 85%;
      }
      
      #messages {
        height: 400px;
      }
      
      .chat-header h5 {
        font-size: 1.1rem;
      }
      
      .user-avatar {
        width: 28px;
        height: 28px;
      }
    }

    @media (max-width: 576px) {
      .message-bubble {
        max-width: 90%;
      }
      
      .input-section {
        padding: 0.75rem;
      }
      
      #messages {
        height: 350px;
        padding: 0.75rem;
      }
    }

    /* Dark theme support */
    @media (prefers-color-scheme: dark) {
      :root {
        --message-bg-other: #2c3e50;
        --light-bg: #34495e;
      }
      
      .chat-container {
        background: #2c3e50;
        color: white;
      }
      
      #messages {
        background: #34495e;
      }
      
      .input-section {
        background: #2c3e50;
        border-top-color: #34495e;
      }
    }

    .timestamp {
      font-size: 0.75rem;
      opacity: 0.7;
      margin-top: 4px;
    }
  </style>
</head>
<body>
  <div class="container py-4">
    <!-- Discussion Topic Creation -->
    <div class="card mb-4 chat-container">
      <div class="card-body">
        <h5 class="card-title"><i class="bi bi-plus-circle me-2"></i>Start a New Discussion</h5>
        <div class="input-group">
          <input type="text" id="topicInput" class="form-control" placeholder="Enter topic title...">
          <button class="btn btn-primary" id="startDiscussionBtn">
            <i class="bi bi-send me-1"></i>Start
          </button>
        </div>
      </div>
    </div>

    <!-- List of Discussions -->
    <div class="card mb-4 chat-container">
      <div class="card-body">
        <h5 class="card-title"><i class="bi bi-chat-dots me-2"></i>Existing Discussions</h5>
        <div id="discussionList" class="list-group list-group-flush">
          <!-- Loaded dynamically -->
        </div>
      </div>
    </div>

    <!-- Messages Section -->
    <div class="card chat-container">
      <div class="chat-header">
        <h5 class="mb-0">
          <i class="bi bi-chat-text me-2"></i>
          Discussion: <span id="currentTopicTitle">Select a discussion to start chatting</span>
        </h5>
      </div>
      
      <div id="messages"></div>
      
      <div class="input-section">
        <!-- Reply Preview -->
        <div id="replyPreview" class="reply-preview">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <small class="text-muted">Replying to <strong id="replyToUser"></strong></small>
              <div id="replyToContent" class="text-truncate"></div>
            </div>
            <button type="button" class="close-reply" id="cancelReply">
              <i class="bi bi-x-lg"></i>
            </button>
          </div>
        </div>

        <!-- Recording Controls -->
        <div id="recordingControls" class="recording-controls">
          <i class="bi bi-record-circle text-danger"></i>
          <span>Recording...</span>
          <span id="recordingTimer" class="recording-timer">00:00</span>
          <button id="cancelRecordingBtn" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-x-circle me-1"></i>Cancel
          </button>
        </div>

        <!-- Message Input -->
        <div class="d-flex align-items-end gap-2">
          <div class="flex-grow-1">
            <textarea id="messageText" class="form-control" rows="1" 
                      placeholder="Type your message..." 
                      style="resize: none; border-radius: 20px;"></textarea>
          </div>
          
          <button class="btn btn-success btn-record" id="sendMessageBtn">
            <i class="bi bi-send"></i>
          </button>
          
          <button class="btn btn-outline-primary btn-record" id="recordAudioBtn">
            <i class="bi bi-mic"></i>
          </button>
        </div>
        
        <canvas id="waveformCanvas" width="100" height="30" class="waveform-container" style="display: none;"></canvas>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title">Delete Message</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">Are you sure you want to delete this message?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger btn-sm" id="confirmDelete">Delete</button>
        </div>
      </div>
    </div>
  </div>

  <!-- JS & Bootstrap -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  
  <script>
    // Global variables
    let replyToId = null;
    let replyToUser = null;
    let replyToContent = null;
    let mediaRecorder;
    let audioChunks = [];
    let recordingTimer;
    let recordingStartTime;
    let messageToDelete = null;

    // Auto-resize textarea
    $('#messageText').on('input', function() {
      this.style.height = 'auto';
      this.style.height = (this.scrollHeight) + 'px';
    });

    // Toggle buttons based on recording state
    function toggleRecordingUI(isRecording) {
      if (isRecording) {
        $('#sendMessageBtn').html('<i class="bi bi-send-fill"></i>').attr('title', 'Send Audio');
        $('#recordAudioBtn').addClass('d-none');
        $('#recordingControls').show();
        $('#waveformCanvas').show();
        startRecordingTimer();
      } else {
        $('#sendMessageBtn').html('<i class="bi bi-send"></i>').attr('title', 'Send Message');
        $('#recordAudioBtn').removeClass('d-none');
        $('#recordingControls').hide();
        $('#waveformCanvas').hide();
        stopRecordingTimer();
      }
    }

    // Recording timer functions
    function startRecordingTimer() {
      recordingStartTime = Date.now();
      recordingTimer = setInterval(() => {
        const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
        const minutes = Math.floor(elapsed / 60).toString().padStart(2, '0');
        const seconds = (elapsed % 60).toString().padStart(2, '0');
        $('#recordingTimer').text(`${minutes}:${seconds}`);
      }, 1000);
    }

    function stopRecordingTimer() {
      if (recordingTimer) {
        clearInterval(recordingTimer);
        recordingTimer = null;
      }
      $('#recordingTimer').text('00:00');
    }

    // Generate user initials for default avatar
    function getUserInitials(name) {
      return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    }

    // Format timestamp
    function formatTimestamp(timestamp) {
      const date = new Date(timestamp);
      const now = new Date();
      const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
      const messageDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
      
      if (messageDate.getTime() === today.getTime()) {
        return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
      } else {
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
      }
    }

    // Send message function
    $('#sendMessageBtn').click(function() {
      // If currently recording, stop and send the audio
      if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        stopVisualization();
        toggleRecordingUI(false);
        return;
      }
      
      // Otherwise, send text message
      const message = $('#messageText').val().trim();
      if (!message) return;

      const selectedDiscussion = $('.discussion-item.active');
      if (!selectedDiscussion.length || !selectedDiscussion.data('id')) {
        alert("Please select a discussion.");
        return;
      }
      const discussionId = selectedDiscussion.data('id');

      sendMessage(discussionId, message, replyToId);
      
      // Reset input and reply state
      $('#messageText').val('').css('height', 'auto');
      clearReplyPreview();
    });

    // Clear reply preview
    function clearReplyPreview() {
      replyToId = null;
      replyToUser = null;
      replyToContent = null;
      $('#replyPreview').hide();
      $('#messageText').attr('placeholder', 'Type your message...');
    }

    // Cancel reply
    $('#cancelReply').click(clearReplyPreview);

    // Show reply preview
    function showReplyPreview(messageId, userName, content) {
      replyToId = messageId;
      replyToUser = userName;
      replyToContent = content;
      
      $('#replyToUser').text(userName);
      $('#replyToContent').text(content.length > 50 ? content.substring(0, 50) + '...' : content);
      $('#replyPreview').show();
      $('#messageText').focus();
    }

    function sendMessage(discussionId, message, replyTo) {
      fetch('/G.A.N.G/discussion/php/send_message.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          discussion_id: discussionId,
          type: 'text',
          content: message,
          reply_to: replyTo
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          loadMessages(discussionId);
        } else {
          alert('Failed to send message: ' + data.message);
        }
      })
      .catch(err => alert('Error: ' + err));
    }

    // Load messages with improved UI
    function loadMessages(discussionId, topicTitle) {
      if (topicTitle) {
        $('#currentTopicTitle').text(topicTitle);
      }

      fetch('/G.A.N.G/discussion/php/get_messages.php?discussion_id=' + discussionId)
        .then(res => res.json())
        .then(messages => {
          console.log('=== DEBUG INFO ===');
          console.log('Current SESSION.userId:', SESSION.userId, typeof SESSION.userId);
          console.log('Messages received:', messages);
          
          const container = $('#messages');
          container.empty();

          // Build message lookup for replies
          const messageMap = {};
          messages.forEach(m => messageMap[m.id] = m);

          messages.forEach(msg => {
            // Now sender_id should be available since we'll fix the backend
            const isCurrentUser = parseInt(msg.sender_id) === parseInt(SESSION.userId);
            const msgDiv = $('<div>').addClass('message-bubble');
            
            if (isCurrentUser) {
              msgDiv.addClass('message-current');
            } else {
              msgDiv.addClass('message-other');
            }

            // Message content container
            const contentDiv = $('<div>').addClass('message-content');

            // User info
            const userInfo = $('<div>').addClass('user-info');
            
            // Avatar with fallback
            let avatarHtml = '';
            if (msg.picture && msg.picture !== '/uploads/users/default.jpg') {
              avatarHtml = `<img src="${msg.picture}" alt="Avatar" class="user-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                           <div class="user-avatar default-avatar" style="display:none;">${getUserInitials(msg.second_name)}</div>`;
            } else {
              avatarHtml = `<div class="user-avatar default-avatar">${getUserInitials(msg.second_name)}</div>`;
            }
            
            const userName = $('<strong>').text(msg.second_name);
            const timestamp = $('<span>').addClass('timestamp').text(formatTimestamp(msg.created_at));
            
            if (isCurrentUser) {
              userInfo.append(userName, timestamp, avatarHtml);
            } else {
              userInfo.append(avatarHtml, userName, timestamp);
            }

            // Reply indicator if this is a reply
            if (msg.reply_to && messageMap[msg.reply_to]) {
              const replyMsg = messageMap[msg.reply_to];
              const replyIndicator = $('<div>').addClass('reply-indicator');
              replyIndicator.html(`<i class="bi bi-reply me-1"></i><strong>${replyMsg.second_name}:</strong> ${replyMsg.content.substring(0, 30)}...`);
              contentDiv.append(replyIndicator);
            }

            // Message content
            let content = '';
            if (msg.type === 'text') {
              content = `<div>${msg.content}</div>`;
            } else if (msg.type === 'audio') {
              content = `<audio controls class="audio-controls"><source src="${msg.content}" type="audio/webm"></audio>`;
            }
            contentDiv.append(content);

            // Message actions
            const actionsDiv = $('<div>').addClass('message-actions');
            
            const replyBtn = $('<button>')
              .addClass('btn btn-sm btn-outline-secondary')
              .html('<i class="bi bi-reply"></i>')
              .attr('title', 'Reply')
              .click(() => showReplyPreview(msg.id, msg.second_name, msg.content));
            
            actionsDiv.append(replyBtn);

            // Delete button only for current user's messages
            if (isCurrentUser) {
              const deleteBtn = $('<button>')
                .addClass('btn btn-sm btn-outline-danger ms-1')
                .html('<i class="bi bi-trash"></i>')
                .attr('title', 'Delete')
                .click(() => {
                  messageToDelete = msg.id;
                  new bootstrap.Modal($('#deleteModal')[0]).show();
                });
              actionsDiv.append(deleteBtn);
            }

            msgDiv.append(userInfo, contentDiv, actionsDiv);
            container.append(msgDiv);
          });

          // Scroll to bottom
          container[0].scrollTop = container[0].scrollHeight;
        })
        .catch(err => console.error('Error loading messages:', err));
    }

    // Delete message confirmation
    $('#confirmDelete').click(function() {
      if (messageToDelete) {
        fetch('/G.A.N.G/discussion/php/delete_message.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ message_id: messageToDelete })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            const discussionId = $('.discussion-item.active').data('id');
            loadMessages(discussionId);
            bootstrap.Modal.getInstance($('#deleteModal')[0]).hide();
          } else {
            alert('Failed to delete message: ' + data.message);
          }
        })
        .catch(err => alert('Error: ' + err));
      }
    });

    // Start Discussion
    $('#startDiscussionBtn').click(function() {
      const topic = $('#topicInput').val().trim();
      if (!topic) return alert("Please enter a topic.");

      fetch('/G.A.N.G/discussion/php/create_discussion.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({ topic: topic })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          $('#topicInput').val('');
          loadDiscussions();
        } else {
          alert('Failed to create topic: ' + data.message);
        }
      })
      .catch(err => console.error('Error creating discussion:', err));
    });

    // Load Discussions
    function loadDiscussions() {
      fetch('/G.A.N.G/discussion/php/get_discussions.php')
        .then(res => res.json())
        .then(data => {
          const list = $('#discussionList');
          list.empty();

          const lastSelectedId = localStorage.getItem('selectedDiscussionId');

          data.forEach(d => {
            const item = $('<div>')
              .addClass('list-group-item discussion-item')
              .html(`
                <div class="d-flex justify-content-between align-items-center">
                  <div>
                    <h6 class="mb-1">${d.topic}</h6>
                    <small class="text-muted">by ${d.second_name}</small>
                  </div>
                  <i class="bi bi-chevron-right"></i>
                </div>
              `)
              .data('id', d.id);

            if (d.id === lastSelectedId) {
              item.addClass('active');
              loadMessages(d.id, d.topic);
            }

            item.click(function() {
              $('.discussion-item').removeClass('active');
              $(this).addClass('active');
              localStorage.setItem('selectedDiscussionId', d.id);
              loadMessages(d.id, d.topic);
            });

            list.append(item);
          });
        })
        .catch(err => console.error('Error loading discussions:', err));
    }

    // Audio Recording Logic
    $('#recordAudioBtn').click(async function() {
      if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        stopVisualization();
        toggleRecordingUI(false);
        return;
      }

      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        visualizeAudio(stream);
        
        mediaRecorder = new MediaRecorder(stream);
        audioChunks = [];
        
        mediaRecorder.ondataavailable = event => {
          audioChunks.push(event.data);
        };

        mediaRecorder.onstop = async () => {
          // Only process audio if not cancelled and we have chunks
          if (!mediaRecorder.cancelled && audioChunks.length > 0) {
            const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
            const base64Audio = await blobToBase64(audioBlob);
            sendAudioMessage(base64Audio);
          }
          
          // Stop all tracks
          stream.getTracks().forEach(track => track.stop());
          audioChunks = []; // Clear chunks after processing
          
          // Reset cancelled flag
          if (mediaRecorder) {
            mediaRecorder.cancelled = false;
          }
        };

        mediaRecorder.start();
        toggleRecordingUI(true);
        
      } catch (err) {
        alert('Audio recording is not supported or permission denied.');
        console.error('Recording error:', err);
      }
    });

    // Cancel recording
    $('#cancelRecordingBtn').click(function() {
      if (mediaRecorder && mediaRecorder.state === 'recording') {
        // Set a flag to prevent audio processing
        mediaRecorder.cancelled = true;
        mediaRecorder.stop();
        stopVisualization();
        toggleRecordingUI(false);
        
        // Stop all media tracks
        if (mediaRecorder && mediaRecorder.stream) {
          mediaRecorder.stream.getTracks().forEach(track => track.stop());
        }
        
        // Clear chunks immediately
        audioChunks = [];
      }
    });

    function blobToBase64(blob) {
      return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onloadend = () => resolve(reader.result);
        reader.readAsDataURL(blob);
      });
    }

    function sendAudioMessage(base64Audio) {
      const selectedDiscussion = $('.discussion-item.active');
      if (!selectedDiscussion.length || !selectedDiscussion.data('id')) {
        alert("Please select a discussion.");
        return;
      }
      const discussionId = selectedDiscussion.data('id');

      fetch('/G.A.N.G/discussion/php/send_audio.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          discussion_id: discussionId,
          type: 'audio',
          content: base64Audio,
          reply_to: replyToId
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          loadMessages(discussionId);
          clearReplyPreview();
        } else {
          alert('Failed to send audio: ' + data.message);
        }
      })
      .catch(err => alert('Error: ' + err));
    }

    // Audio visualization
    let audioCtx, analyser, dataArray, canvasCtx, animationId;

    function visualizeAudio(stream) {
      audioCtx = new (window.AudioContext || window.webkitAudioContext)();
      analyser = audioCtx.createAnalyser();

      const source = audioCtx.createMediaStreamSource(stream);
      source.connect(analyser);

      const canvas = $('#waveformCanvas')[0];
      canvasCtx = canvas.getContext('2d');
      analyser.fftSize = 64;

      const bufferLength = analyser.frequencyBinCount;
      dataArray = new Uint8Array(bufferLength);

      function draw() {
        animationId = requestAnimationFrame(draw);
        analyser.getByteFrequencyData(dataArray);

        canvasCtx.clearRect(0, 0, canvas.width, canvas.height);
        const barWidth = (canvas.width / bufferLength) * 1.5;

        let x = 0;
        for (let i = 0; i < bufferLength; i++) {
          const barHeight = dataArray[i] / 2;
          canvasCtx.fillStyle = '#007bff';
          canvasCtx.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
          x += barWidth + 1;
        }
      }
      draw();
    }

    function stopVisualization() {
      if (animationId) cancelAnimationFrame(animationId);
      if (audioCtx) audioCtx.close();
      const canvas = $('#waveformCanvas')[0];
      if (canvas) {
        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
      }
    }

    // Enter key to send message (Shift+Enter for new line)
    $('#messageText').keydown(function(e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        $('#sendMessageBtn').click();
      }
    });

    // Initialize on page load
    $(document).ready(function() {
      loadDiscussions();
      
      // Auto-focus message input when discussion is selected
      $(document).on('click', '.discussion-item', function() {
        setTimeout(() => $('#messageText').focus(), 100);
      });
    });

    // Session variables from PHP
    const SESSION = {
      userId: <?php echo json_encode($_SESSION['user_id']); ?>,
      secondName: <?php echo json_encode($_SESSION['second_name']); ?>,
      picture: <?php echo json_encode($_SESSION['picture']); ?>
    };

    // For backward compatibility
    const CURRENT_USER_ID = SESSION.userId;
  </script>
</body>
</html>