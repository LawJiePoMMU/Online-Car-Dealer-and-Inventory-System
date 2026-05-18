const ajaxUrl = window.location.href;
let pollInterval;
let directoryUsers = [];
let currentDirTab = 'Admin'; 

function getAvatarHTML(name, avatarUrl, size = 42) {
    if (avatarUrl && avatarUrl !== 'NULL' && avatarUrl !== '') {
        return `<img src="${avatarUrl}" style="width:${size}px; height:${size}px; border-radius:50%; object-fit:cover; display:block;">`;
    } else {
        let parts = name ? name.trim().split(' ') : ['G'];
        let initials = '';
        parts.forEach(p => { if (p) initials += p.charAt(0).toUpperCase(); });
        initials = initials.substring(0, 3);
        return `<div class='avatar' style='width: ${size}px; height: ${size}px; font-size: ${size * 0.42}px; font-weight: 700; letter-spacing: 0.5px; display: inline-flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 50%; background-color: #e0e7ff; color: #1e3a8a; flex-shrink: 0;'>${initials}</div>`;
    }
}

document.addEventListener("DOMContentLoaded", function () {
    loadUsers();

    document.getElementById('searchContact').addEventListener('input', function () {
        let term = this.value.toLowerCase();
        document.querySelectorAll('#chatList .contact-item').forEach(item => {
            let name = item.querySelector('.contact-name').innerText.toLowerCase();
            item.style.display = name.includes(term) ? 'flex' : 'none';
        });
    });

    document.getElementById('newContactSearch').addEventListener('input', renderDirectory);
    document.getElementById('sendBtn').addEventListener('click', sendMessage);
    document.getElementById('messageInput').addEventListener('keypress', function (e) {
        if (e.key === 'Enter') sendMessage();
    });

    document.getElementById('fileInput').addEventListener('change', function () {
        let previewContainer = document.getElementById('filePreviewContainer');
        if (!previewContainer) return;
        previewContainer.innerHTML = '';

        if (this.files.length > 0) {
            previewContainer.style.display = 'flex';
            document.getElementById('sendIcon').style.color = '#10b981';
            Array.from(this.files).forEach(file => {
                let div = document.createElement('div');
                div.style.cssText = 'position:relative; width:80px; height:80px; border-radius:8px; overflow:hidden; background-color:#fff; border:1px solid #d1d5db; display:flex; flex-direction:column; align-items:center; justify-content:center; flex-shrink:0; padding:4px;';
                if (file.type.startsWith('image/')) {
                    let reader = new FileReader();
                    reader.onload = function (e) { div.innerHTML = `<img src="${e.target.result}" style="width:100%; height:100%; object-fit:cover; border-radius:4px;">`; }
                    reader.readAsDataURL(file);
                } else {
                    div.innerHTML = `<i class="fas fa-file-alt" style="font-size: 28px; color: #6b7280; margin-bottom: 6px;"></i><span style="font-size: 10px; color: #4b5563; width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-align: center;" title="${file.name}">${file.name}</span>`;
                }
                previewContainer.appendChild(div);
            });
        } else {
            previewContainer.style.display = 'none';
            document.getElementById('sendIcon').style.color = '#9ca3af';
        }
    });
});

function switchTab(tabName, btn) {
    document.querySelectorAll('.chat-search-wrap .chat-tabs .chat-tab-btn').forEach(b => {
        b.classList.remove('active');
    });
    btn.classList.add('active');

    document.querySelectorAll('#chatList .contact-item').forEach(item => {
        let type = item.dataset.type;
        if (tabName === 'all') {
            item.style.display = 'flex';
        } else if (tabName === 'unread') {
            item.style.display = item.querySelector('.unread-badge') ? 'flex' : 'none';
        } else if (tabName === 'group') {
            item.style.display = (type === 'group') ? 'flex' : 'none';
        }
    });
}

function loadUsers() {
    let formData = new FormData();
    formData.append('action', 'fetch_users');

    fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            let list = document.getElementById('chatList');
            list.innerHTML = '';

            if (data.users && data.users.length > 0) {
                data.users.forEach((u, index) => {
                    let phone = u.user_phone ? u.user_phone : '';
                    let avatarHTML = getAvatarHTML(u.user_name, u.user_avatar);
                    let unreadBadge = index === 0 ? `<div class="unread-badge">New</div>` : '';
                    let div = document.createElement('div');
                    div.className = 'contact-item';
                    div.dataset.id = u.user_id;
                    div.dataset.type = 'user';
                    div.onclick = () => openChat(u.user_id, 'user', u.user_name, u.user_avatar, phone, div);
                    div.innerHTML = `<div class="avatar-wrap">${avatarHTML}</div><div style="flex:1;"><div class="contact-name" style="font-size: 15px; font-weight: 600; color: #111827;">${u.user_name}</div><div class="contact-msg" style="font-size: 13px; color: #6b7280;">${phone}</div></div>${unreadBadge}`;
                    list.appendChild(div);
                });
            }

            if (data.groups && data.groups.length > 0) {
                data.groups.forEach(g => {
                    let avatarHTML = getAvatarHTML(g.group_name, null);
                    let div = document.createElement('div');
                    div.className = 'contact-item';
                    div.dataset.id = g.group_id;
                    div.dataset.type = 'group';
                    div.onclick = () => openChat(g.group_id, 'group', g.group_name, null, 'Group Chat', div);
                    div.innerHTML = `<div class="avatar-wrap">${avatarHTML}</div><div style="flex:1;"><div class="contact-name" style="font-size: 15px; font-weight: 600; color: #111827;">${g.group_name}</div><div class="contact-msg" style="font-size: 13px; color: #6b7280;">Group Chat</div></div>`;
                    list.appendChild(div);
                });
            }
        });
}

function openNewChatModal() {
    document.getElementById('newChatModal').classList.add('active');
    document.getElementById('newContactSearch').value = '';
    let formData = new FormData();
    formData.append('action', 'fetch_directory');
    fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            directoryUsers = data;
            renderDirectory();
        });
}

function closeNewChatModal() {
    document.getElementById('newChatModal').classList.remove('active');
}

function switchDirectoryTab(role) {
    currentDirTab = role;
    if(document.getElementById('tabDirAdmin')) document.getElementById('tabDirAdmin').classList.toggle('active', role === 'Admin');
    if(document.getElementById('tabDirCustomer')) document.getElementById('tabDirCustomer').classList.toggle('active', role === 'Customer');
    renderDirectory();
}

function renderDirectory() {
    let list = document.getElementById('newContactList');
    list.innerHTML = '';
    let term = document.getElementById('newContactSearch').value.toLowerCase();

    let filtered = directoryUsers.filter(u =>
        (u.user_role === currentDirTab) &&
        (u.user_name.toLowerCase().includes(term) || (u.user_phone && u.user_phone.includes(term)))
    );

    if (filtered.length === 0) {
        list.innerHTML = '<div style="text-align:center; color:#9ca3af; padding: 30px;">No users found.</div>';
        return;
    }

    let grouped = {};
    filtered.forEach(u => {
        let firstLetter = u.user_name.charAt(0).toUpperCase();
        if (!firstLetter.match(/[A-Z]/)) firstLetter = '#';
        if (!grouped[firstLetter]) grouped[firstLetter] = [];
        grouped[firstLetter].push(u);
    });

    let keys = Object.keys(grouped).sort();
    keys.forEach(k => {
        list.innerHTML += `<div style="padding: 6px 20px; background: #f9fafb; font-weight: 700; color: #1e3a8a; position: sticky; top: 0; z-index: 1; border-bottom: 1px solid #e5e7eb;">${k}</div>`;
        grouped[k].forEach(u => {
            let phone = u.user_phone || 'No Phone';
            let avatarHTML = getAvatarHTML(u.user_name, u.user_avatar, 40);
            let safeName = u.user_name.replace(/'/g, "\\'");
            list.innerHTML += `<div class="contact-item" style="padding: 12px 20px; border-bottom: 1px solid #f3f4f6;" onclick="closeNewChatModal(); startNewChat(${u.user_id}, '${safeName}', '${u.user_avatar || ''}', '${phone}');"><div class="avatar-wrap">${avatarHTML}</div><div style="flex:1;"><div class="contact-name" style="font-weight:600; color: #111827;">${u.user_name}</div><div style="font-size:12px; color:#6b7280;">${phone}</div></div></div>`;
        });
    });
}

function startNewChat(id, name, avatar, phone) {
    let existingItem = Array.from(document.querySelectorAll('#chatList .contact-item')).find(el => el.dataset.id == id && el.dataset.type == 'user');
    if (existingItem) {
        existingItem.click();
    } else {
        let list = document.getElementById('chatList');
        let div = document.createElement('div');
        div.className = 'contact-item active';
        div.dataset.id = id;
        div.dataset.type = 'user';
        div.onclick = () => openChat(id, 'user', name, avatar, phone, div);
        let avatarHTML = getAvatarHTML(name, avatar);
        div.innerHTML = `<div class="avatar-wrap">${avatarHTML}</div><div style="flex:1;"><div class="contact-name" style="font-weight:600;">${name}</div><div style="font-size:13px; color:#6b7280;">New Chat</div></div>`;
        list.insertBefore(div, list.firstChild);
        div.click();
    }
}

let currentGroupTab = 'Admin';
let groupDirectoryUsers = [];
let selectedGroupMembers = new Set();

function toggleGroupMember(cb) {
    if (cb.checked) selectedGroupMembers.add(cb.value);
    else selectedGroupMembers.delete(cb.value);
}

function openCreateGroupModal() {
    document.getElementById('createGroupModal').classList.add('active');
    document.getElementById('groupNameInput').value = '';
    selectedGroupMembers.clear();

    let formData = new FormData();
    formData.append('action', 'fetch_directory');
    fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            groupDirectoryUsers = data;
            renderGroupDirectory();
        });
}

function switchGroupTab(role) {
    currentGroupTab = role;
    if(document.getElementById('tabGroupAdmin')) document.getElementById('tabGroupAdmin').classList.toggle('active', role === 'Admin');
    if(document.getElementById('tabGroupCustomer')) document.getElementById('tabGroupCustomer').classList.toggle('active', role === 'Customer');
    renderGroupDirectory();
}

function renderGroupDirectory() {
    let list = document.getElementById('groupMembersList');
    list.innerHTML = '';

    let filtered = groupDirectoryUsers.filter(u => u.user_role === currentGroupTab);

    if (filtered.length === 0) {
        list.innerHTML = '<div style="text-align:center; color:#9ca3af; padding: 30px;">No users available.</div>';
        return;
    }

    let grouped = {};
    filtered.forEach(u => {
        let firstLetter = u.user_name.charAt(0).toUpperCase();
        if (!firstLetter.match(/[A-Z]/)) firstLetter = '#';
        if (!grouped[firstLetter]) grouped[firstLetter] = [];
        grouped[firstLetter].push(u);
    });

    let keys = Object.keys(grouped).sort();
    keys.forEach(k => {
        list.innerHTML += `<div style="padding: 6px 20px; background: #f9fafb; font-weight: 700; color: #1e3a8a; position: sticky; top: 0; z-index: 1; border-bottom: 1px solid #e5e7eb;">${k}</div>`;
        grouped[k].forEach(u => {
            let avatarHTML = getAvatarHTML(u.user_name, u.user_avatar, 36);
            let isChecked = selectedGroupMembers.has(u.user_id.toString()) ? 'checked' : '';
            list.innerHTML += `
                <label style="display:flex; align-items:center; padding: 10px 20px; border-bottom:1px solid #f3f4f6; cursor:pointer;">
                    <input type="checkbox" class="group-member-cb" value="${u.user_id}" onchange="toggleGroupMember(this)" ${isChecked} style="margin-right:15px; width:16px; height:16px;">
                    <div style="margin-right: 12px;">${avatarHTML}</div>
                    <span style="font-weight: 600; color: #111827;">${u.user_name}</span>
                </label>`;
        });
    });
}

function closeCreateGroupModal() {
    document.getElementById('createGroupModal').classList.remove('active');
}

function submitCreateGroup() {
    let name = document.getElementById('groupNameInput').value.trim();
    if (name === '') { Swal.fire('Error', 'Please enter a group name', 'error'); return; }
    let memberIds = Array.from(selectedGroupMembers);
    if (memberIds.length === 0) { Swal.fire('Error', 'Please select at least one member', 'error'); return; }
    let formData = new FormData();
    formData.append('action', 'create_group');
    formData.append('group_name', name);
    formData.append('members', JSON.stringify(memberIds));
    fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire('Success', 'Group created!', 'success');
                closeCreateGroupModal();
                loadUsers();
            }
        });
}

function openChat(id, type, name, avatarUrl, phone, element) {
    sessionStorage.setItem('lastActiveChatId', id);
    sessionStorage.setItem('lastActiveChatType', type);
    document.getElementById('currentChatId').value = id;
    document.getElementById('currentChatType').value = type;
    document.getElementById('emptyChatArea').style.display = 'none';
    document.getElementById('mainChatArea').style.display = 'flex';
    document.getElementById('activeName').innerText = name;
    document.getElementById('activePhone').innerText = phone;
    document.getElementById('headerAvatarContainer').innerHTML = getAvatarHTML(name, avatarUrl, 40);
    document.getElementById('profileName').innerText = name;
    document.getElementById('profilePhone').innerText = phone;
    document.getElementById('profileAvatarContainer').innerHTML = getAvatarHTML(name, avatarUrl, 120);

    document.querySelectorAll('#chatList .contact-item').forEach(el => el.classList.remove('active'));
    if (element) {
        element.classList.add('active');
        let badge = element.querySelector('.unread-badge');
        if (badge) badge.remove();
        let bellIcon = document.getElementById('notifBellIcon');
        let uniqueId = type + '_' + id;
        let mutedChats = JSON.parse(localStorage.getItem('mutedChats')) || [];
        if (bellIcon) {
            if (mutedChats.includes(uniqueId)) {
                bellIcon.className = 'fas fa-bell-slash';
                bellIcon.style.color = '#9ca3af';
            } else {
                bellIcon.className = 'fas fa-bell';
                bellIcon.style.color = '#f59e0b';
            }
        }
    }
    loadMessages(id, type);
    loadProfileMedia(id, type);
    loadProfileDetails(id, type);
    if (pollInterval) clearInterval(pollInterval);
    pollInterval = setInterval(() => loadMessages(id, type, false), 5000);
}

function toggleProfileSidebar() {
    document.getElementById('profileSidebar').classList.toggle('open');
}

function loadProfileMedia(id, type) {
    let formData = new FormData();
    formData.append('action', 'fetch_shared_media');
    formData.append('other_id', id);
    formData.append('chat_type', type);

    fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            let grid = document.getElementById('profileMediaGrid');
            let docsList = document.getElementById('profileDocsList');
            grid.innerHTML = '';
            docsList.innerHTML = '';

            if (data.media && data.media.length > 0) {
                let hasMedia = false, hasDocs = false;
                data.media.forEach(path => {
                    let rawName = path.split('/').pop();
                    let fileName = rawName.includes('_') ? rawName.substring(rawName.indexOf('_') + 1) : rawName;

                    if (path.match(/\.(jpeg|jpg|gif|png|webp)$/i)) {
                        grid.innerHTML += `<img src="${path}" class="media-item" style="cursor:pointer;" onclick="openLightbox(this.src)">`;
                        hasMedia = true;
                    } else if (path.match(/\.(pdf)$/i)) {
                        docsList.innerHTML += `<a href="${path}" target="_blank" style="display:flex; align-items:center; background:#fee2e2; color:#ef4444; padding:8px 12px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:bold;"><i class="fas fa-file-pdf" style="font-size:16px; margin-right:8px;"></i>${fileName}</a>`;
                        hasDocs = true;
                    } else {
                        docsList.innerHTML += `<a href="${path}" target="_blank" style="display:flex; align-items:center; background:#f3f4f6; color:#4b5563; padding:8px 12px; border-radius:6px; text-decoration:none; font-size:12px; font-weight:bold;"><i class="fas fa-file" style="font-size:16px; margin-right:8px;"></i>${fileName}</a>`;
                        hasDocs = true;
                    }
                });
                if (!hasMedia) grid.innerHTML = `<div style="grid-column: span 3; color: #9ca3af; font-size: 12px;">No images.</div>`;
                if (!hasDocs) docsList.innerHTML = `<div style="color: #9ca3af; font-size: 12px;">No documents.</div>`;
            } else {
                grid.innerHTML = `<div style="grid-column: span 3; color: #9ca3af; font-size: 12px;">No media shared yet.</div>`;
                docsList.innerHTML = `<div style="color: #9ca3af; font-size: 12px;">No documents shared yet.</div>`;
            }
        });
}

function loadMessages(id, type, scrollToBottom = true) {
    let formData = new FormData();
    formData.append('action', 'fetch_messages');
    formData.append('other_id', id);
    formData.append('chat_type', type);
    fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            let area = document.getElementById('messagesArea');
            let isAtBottom = area.scrollHeight - area.scrollTop <= area.clientHeight + 50;
            area.innerHTML = '';
            if (data.messages) {
                data.messages.forEach(m => {
                    let myId = document.getElementById('myUserId').value;
                    let isSent = (m.sender_id == myId) ? true : false;
                    let senderName = (type === 'group' && !isSent) ? `<div style="font-size:11px; font-weight:bold; color:#f59e0b; margin-bottom:4px;">${m.user_name}</div>` : '';
                    let msgTime = new Date(m.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                    let div = document.createElement('div');
                    div.className = `message-row ${isSent ? 'outgoing' : 'incoming'}`;
                    let contentHTML = m.message ? `<div>${m.message}</div>` : '';
                    if (m.file_path) {
                        let rawName = m.file_path.split('/').pop();
                        let fileName = rawName.includes('_') ? rawName.substring(rawName.indexOf('_') + 1) : rawName;
                        if (m.file_path.match(/\.(jpeg|jpg|gif|png|webp)$/i)) {
                            contentHTML += `<img src="${m.file_path}" style="max-width: 220px; border-radius: 8px; margin-top: 8px; cursor:pointer;" onclick="openLightbox(this.src)">`;
                        } else if (m.file_path.match(/\.(pdf)$/i)) {
                            contentHTML += `<div style="margin-top: 8px; background: rgba(0,0,0,0.05); padding: 8px; border-radius: 6px;"><i class="fas fa-file-pdf" style="color:#ef4444; margin-right:8px;"></i> <a href="${m.file_path}" target="_blank" style="color: inherit; text-decoration: none; font-weight: bold; font-size: 13px;">${fileName}</a></div>`;
                        } else {
                            contentHTML += `<div style="margin-top: 8px; background: rgba(0,0,0,0.05); padding: 8px; border-radius: 6px;"><i class="fas fa-file" style="color:#6b7280; margin-right:8px;"></i> <a href="${m.file_path}" target="_blank" style="color: inherit; text-decoration: none; font-weight: bold; font-size: 13px;">${fileName}</a></div>`;
                        }
                    }
                    div.innerHTML = `<div class="message-bubble">${senderName}${contentHTML}<span class="message-time">${msgTime}</span></div>`;
                    area.appendChild(div);
                });
                if (scrollToBottom || isAtBottom) {
                    area.scrollTop = area.scrollHeight;
                }
            }
        });
}

function sendMessage() {
    let id = document.getElementById('currentChatId').value;
    let type = document.getElementById('currentChatType').value;
    let msg = document.getElementById('messageInput').value.trim();
    let files = document.getElementById('fileInput').files;

    if (msg === '' && files.length === 0) return;

    let formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('other_id', id);
    formData.append('chat_type', type);
    formData.append('message', msg);
    for (let i = 0; i < files.length; i++) { formData.append('files[]', files[i]); }

    fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('messageInput').value = '';
                document.getElementById('fileInput').value = '';
                document.getElementById('sendIcon').style.color = '#9ca3af';
                let previewContainer = document.getElementById('filePreviewContainer');
                if (previewContainer) {
                    previewContainer.style.display = 'none';
                    previewContainer.innerHTML = '';
                }
                loadMessages(id, type);
                loadProfileMedia(id, type);
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
}

function dismissNotification() {
    let currentId = document.getElementById('currentChatId').value;
    let currentType = document.getElementById('currentChatType').value;
    let uniqueId = currentType + '_' + currentId; 

    let bellIcon = document.getElementById('notifBellIcon');
    let mutedChats = JSON.parse(localStorage.getItem('mutedChats')) || [];

    if (bellIcon) {
        if (bellIcon.classList.contains('fa-bell-slash')) {
            bellIcon.className = 'fas fa-bell';
            bellIcon.style.color = '#f59e0b';
            mutedChats = mutedChats.filter(id => id !== uniqueId);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Notifications unmuted', showConfirmButton: false, timer: 2000 });
        } else {
            bellIcon.className = 'fas fa-bell-slash';
            bellIcon.style.color = '#9ca3af';
            if (!mutedChats.includes(uniqueId)) mutedChats.push(uniqueId);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Notifications muted', showConfirmButton: false, timer: 2000 });
        }
    }

    localStorage.setItem('mutedChats', JSON.stringify(mutedChats)); 

    let activeItem = Array.from(document.querySelectorAll('#chatList .contact-item')).find(el => el.dataset.id == currentId && el.dataset.type == currentType);
    if (activeItem) {
        let badge = activeItem.querySelector('.unread-badge');
        if (badge) badge.remove();
    }
}

function confirmAction(type) {
    let id = document.getElementById('currentChatId').value;
    let chatType = document.getElementById('currentChatType').value;
    if (!id) return;

    let isGroup = (chatType === 'group');
    let titleText = type === 'clear' ? 'Clear all messages?' : (isGroup ? 'Leave Group?' : 'Delete Chat?');
    let textDesc = type === 'clear' ? 'This empties the conversation.' : (isGroup ? 'You will leave this group chat.' : 'This removes the conversation entirely.');

    Swal.fire({
        title: titleText,
        text: textDesc,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: type === 'clear' ? '#f59e0b' : '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Yes, proceed!'
    }).then((result) => {
        if (result.isConfirmed) {
            let formData = new FormData();
            formData.append('action', type === 'clear' ? 'clear_chat' : 'delete_chat');
            formData.append('other_id', id);
            formData.append('chat_type', chatType);

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        if (type === 'clear') {
                            loadMessages(id, chatType);
                        } else {
                            document.getElementById('mainChatArea').style.display = 'none';
                            document.getElementById('emptyChatArea').style.display = 'flex';
                            sessionStorage.removeItem('lastActiveChatId');
                            sessionStorage.removeItem('lastActiveChatType');
                            loadUsers();
                        }
                        Swal.fire('Done!', '', 'success');
                    }
                });
        }
    });
}

function loadProfileDetails(id, type) {
    let formData = new FormData();
    formData.append('action', 'fetch_profile_details');
    formData.append('other_id', id);
    formData.append('chat_type', type);

    fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            let title = document.getElementById('profileDynamicTitle');
            let list = document.getElementById('profileDynamicList');

            if (data.type === 'user') {
                title.innerText = 'Groups in common';
                list.style.background = '#f3f4f6';
                list.style.padding = '12px';
                list.innerHTML = data.data.length > 0 ? data.data.join(', ') : 'No groups in common';
            } else {
                title.innerText = 'Group Members';
                list.style.background = 'transparent';
                list.style.padding = '0';

                let membersHTML = '';
                if (data.data.length > 0) {
                    data.data.forEach(name => {
                        let initial = name.charAt(0).toUpperCase();
                        membersHTML += `
                            <div style="display:flex; align-items:center; padding:8px 0; border-bottom:1px solid #f3f4f6;">
                                <div style="width:28px; height:28px; border-radius:50%; background:#e0e7ff; color:#1e3a8a; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:bold; margin-right:10px;">${initial}</div>
                                <span style="font-size:13px; color:#111827; font-weight:600;">${name}</span>
                            </div>`;
                    });
                } else {
                    membersHTML = '<div style="padding: 12px; background: #f3f4f6; border-radius: 8px;">No members</div>';
                }
                let addBtnHTML = `<button onclick="openAddMemberModal()" style="margin-top: 15px; width: 100%; padding: 10px; border-radius: 8px; border: 1px dashed #1e3a8a; background: transparent; color: #1e3a8a; cursor: pointer; font-weight: 600; transition: 0.2s;"><i class="fas fa-user-plus" style="margin-right: 6px;"></i> Add Member</button>`;
                list.innerHTML = membersHTML + addBtnHTML;
            }
        });
}

let addMemberUsers = [];
let currentAddTab = 'Admin';
let selectedAddMembers = new Set();

function openAddMemberModal() {
    document.getElementById('addMemberModal').classList.add('active');
    selectedAddMembers.clear();
    let groupId = document.getElementById('currentChatId').value;

    let formData = new FormData();
    formData.append('action', 'fetch_non_group_members');
    formData.append('group_id', groupId);
    fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            addMemberUsers = data;
            renderAddMemberDirectory();
        });
}

function closeAddMemberModal() { document.getElementById('addMemberModal').classList.remove('active'); }

function switchAddMemberTab(role) {
    currentAddTab = role;
    if(document.getElementById('tabAddAdmin')) document.getElementById('tabAddAdmin').classList.toggle('active', role === 'Admin');
    if(document.getElementById('tabAddCustomer')) document.getElementById('tabAddCustomer').classList.toggle('active', role === 'Customer');
    renderAddMemberDirectory();
}

function toggleAddMember(cb) {
    if (cb.checked) selectedAddMembers.add(cb.value);
    else selectedAddMembers.delete(cb.value);
}

function renderAddMemberDirectory() {
    let list = document.getElementById('addMembersList');
    list.innerHTML = '';
    
    let filtered = addMemberUsers.filter(u => u.user_role === currentAddTab);

    if (filtered.length === 0) {
        list.innerHTML = '<div style="text-align:center; color:#9ca3af; padding: 30px;">No available users to add.</div>';
        return;
    }

    let grouped = {};
    filtered.forEach(u => {
        let firstLetter = u.user_name.charAt(0).toUpperCase();
        if (!firstLetter.match(/[A-Z]/)) firstLetter = '#';
        if (!grouped[firstLetter]) grouped[firstLetter] = [];
        grouped[firstLetter].push(u);
    });

    let keys = Object.keys(grouped).sort();
    keys.forEach(k => {
        list.innerHTML += `<div style="padding: 6px 20px; background: #f9fafb; font-weight: 700; color: #1e3a8a; position: sticky; top: 0; z-index: 1; border-bottom: 1px solid #e5e7eb;">${k}</div>`;
        grouped[k].forEach(u => {
            let avatarHTML = getAvatarHTML(u.user_name, u.user_avatar, 36);
            let isChecked = selectedAddMembers.has(u.user_id.toString()) ? 'checked' : '';
            list.innerHTML += `
                <label style="display:flex; align-items:center; padding: 10px 20px; border-bottom:1px solid #f3f4f6; cursor:pointer;">
                    <input type="checkbox" class="add-member-cb" value="${u.user_id}" onchange="toggleAddMember(this)" ${isChecked} style="margin-right:15px; width:16px; height:16px;">
                    <div style="margin-right: 12px;">${avatarHTML}</div>
                    <span style="font-weight: 600; color: #111827;">${u.user_name}</span>
                </label>`;
        });
    });
}

function submitAddMembers() {
    let memberIds = Array.from(selectedAddMembers);
    if (memberIds.length === 0) { Swal.fire('Error', 'Please select at least one member', 'error'); return; }

    let groupId = document.getElementById('currentChatId').value;
    let formData = new FormData();
    formData.append('action', 'add_group_members');
    formData.append('group_id', groupId);
    formData.append('members', JSON.stringify(memberIds));

    fetch(ajaxUrl, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                Swal.fire('Success', 'Members successfully added!', 'success');
                closeAddMemberModal();
                loadProfileDetails(groupId, 'group');
            }
        });
}

function openLightbox(src) {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('lightboxDownload').href = src;
    document.getElementById('imageLightbox').style.display = 'flex';
}