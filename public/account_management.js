document.addEventListener('DOMContentLoaded', async function() {
    const userListContainer = document.getElementById('user-list-container');
    const addUserBtn = document.getElementById('add-user-btn');
    const userModal = document.getElementById('user-modal');
    const closeModalBtn = userModal.querySelector('.close-btn');
    const userForm = document.getElementById('user-form');
    const modalTitle = document.getElementById('modal-title');

    // --- API Calls ---
    async function fetchApiData(url, method = 'GET', data = null) {
        try {
            const options = { method };
            if (data) {
                options.headers = { 'Content-Type': 'application/json' };
                options.body = JSON.stringify(data);
            }
            const response = await fetch(url, options);
            const result = await response.json(); // Try to parse JSON regardless of response.ok
            if (!response.ok) {
                throw new Error(result.message || `Network response was not ok: ${response.status}`);
            }
            return result;
        } catch (error) {
            console.error('API Error:', error);
            alert('API通信エラー: ' + error.message);
            return { status: 'error', message: error.message };
        }
    }

    // --- User List Rendering ---
    async function renderUserList() {
        const result = await fetchApiData('user_api.php'); // GET is default
        if (result.status === 'success') {
            let tableHtml = '<table><thead><tr><th>ID</th><th>氏名</th><th>ユーザー名</th><th>役割</th><th>並び順</th><th>Access_id</th><th>操作</th></tr></thead><tbody>';
            result.data.forEach(user => {
                tableHtml += `
                    <tr>
                        <td>${user.id}</td>
                        <td>${user.name}</td>
                        <td>${user.username}</td>
                        <td>${user.role}</td>
                        <td>${user.display_order}</td>
                        <td>${user.Access_id || ''}</td>
                        <td>
                            <button class="edit-user-btn" data-user-id="${user.id}">編集</button>
                            <button class="delete-user-btn" data-user-id="${user.id}">削除</button>
                        </td>
                    </tr>
                `;
            });
            tableHtml += '</tbody></table>';
            userListContainer.innerHTML = tableHtml;

            // Add event listeners for edit/delete buttons
            document.querySelectorAll('.edit-user-btn').forEach(button => {
                button.addEventListener('click', async (event) => {
                    const userId = event.target.dataset.userId;
                    const userToEdit = result.data.find(user => user.id == userId);
                    if (userToEdit) {
                        openUserModal(userToEdit);
                    }
                });
            });

            document.querySelectorAll('.delete-user-btn').forEach(button => {
                button.addEventListener('click', async (event) => {
                    const userId = event.target.dataset.userId;
                    if (confirm('本当にこのユーザーを削除しますか？')) {
                        const deleteResult = await fetchApiData('user_api.php', 'DELETE', { user_id: userId });
                        if (deleteResult.status === 'success') {
                            alert(deleteResult.message);
                            renderUserList();
                        } else {
                            alert('削除失敗: ' + deleteResult.message);
                        }
                    }
                });
            });

        } else {
            userListContainer.innerHTML = `<p>ユーザーの読み込みに失敗しました: ${result.message}</p>`;
        }
    }

    // --- Modal Handling ---
    const openUserModal = (user = null) => {
        userForm.reset(); // Clear form
        userForm.elements.user_id.value = ''; // Clear hidden ID

        if (user) { // Edit mode
            modalTitle.textContent = 'ユーザーを編集';
            userForm.elements.user_id.value = user.id;
            userForm.elements.name.value = user.name;
            userForm.elements.username.value = user.username;
            userForm.elements.role.value = user.role;
            userForm.elements.display_order.value = user.display_order;
            userForm.elements.Access_id.value = user.Access_id;
            userForm.elements.password.required = false; // Password not required for edit
            userForm.elements.password.placeholder = '変更する場合のみ入力';
        } else { // Add mode
            modalTitle.textContent = '新しいユーザーを追加';
            userForm.elements.display_order.value = '9999'; // Default value
            userForm.elements.Access_id.value = '';
            userForm.elements.password.required = true; // Password required for new user
            userForm.elements.password.placeholder = '';
        }
        userModal.style.display = 'block';
    };

    const closeUserModal = () => {
        userModal.style.display = 'none';
        userForm.reset();
    };

    // --- Event Listeners ---
    addUserBtn.addEventListener('click', () => openUserModal());
    closeModalBtn.addEventListener('click', closeUserModal);
    window.addEventListener('click', (event) => {
        if (event.target == userModal) {
            closeUserModal();
        }
    });

    // --- Form Submission ---
    userForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        const userId = userForm.elements.user_id.value;
        const method = userId ? 'PUT' : 'POST';
        const url = 'user_api.php';

        const userData = {
            name: userForm.elements.name.value,
            username: userForm.elements.username.value,
            role: userForm.elements.role.value,
            display_order: parseInt(userForm.elements.display_order.value, 10) || 9999,
            Access_id: userForm.elements.Access_id.value,
        };
        if (userForm.elements.password.value) { // Only include password if provided
            userData.password = userForm.elements.password.value;
        }
        if (userId) {
            userData.user_id = userId;
        }

        const result = await fetchApiData(url, method, userData);
        if (result.status === 'success') {
            alert(result.message);
            closeUserModal();
            renderUserList(); // Refresh list
        } else {
            alert('操作失敗: ' + result.message);
        }
    });

    // --- Initial Load ---
    renderUserList();
});