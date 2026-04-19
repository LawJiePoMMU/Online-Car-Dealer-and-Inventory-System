document.addEventListener('DOMContentLoaded', function () {
    const tabButtons = document.querySelectorAll('.inner-tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            e.preventDefault();

            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            this.classList.add('active');
            const targetId = this.getAttribute('data-target');
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.classList.add('active');
            }
        });
    });

    const scoreInputs = document.querySelectorAll('.score-input');
    const scoreAvgInput = document.getElementById('score_avg');

    if (scoreInputs.length > 0 && scoreAvgInput) {
        scoreInputs.forEach(input => {
            input.addEventListener('input', function () {
                let ext = parseFloat(document.getElementById('score_ext').value) || 0;
                let int = parseFloat(document.getElementById('score_int').value) || 0;
                let mech = parseFloat(document.getElementById('score_mech').value) || 0;
                let tyre = parseFloat(document.getElementById('score_tyre').value) || 0;

                ext = Math.max(0, Math.min(5, ext));
                int = Math.max(0, Math.min(5, int));
                mech = Math.max(0, Math.min(5, mech));
                tyre = Math.max(0, Math.min(5, tyre));

                document.getElementById('score_ext').value = ext;
                document.getElementById('score_int').value = int;
                document.getElementById('score_mech').value = mech;
                document.getElementById('score_tyre').value = tyre;

                let avg = (ext + int + mech + tyre) / 4;
                scoreAvgInput.value = avg.toFixed(1);
            });
        });
    }

    const locations = {
        "Johor": ["Johor Bahru", "Batu Pahat", "Kluang", "Kulai", "Muar", "Segamat", "Pontian", "Kota Tinggi", "Mersing", "Tangkak"],
        "Kedah": ["Alor Setar", "Sungai Petani", "Kulim", "Baling", "Langkawi", "Kubang Pasu", "Yan", "Padang Terap", "Sik"],
        "Kelantan": ["Kota Bharu", "Pasir Mas", "Tumpat", "Bachok", "Tanah Merah", "Machang", "Kuala Krai", "Gua Musang"],
        "Kuala Lumpur": ["Kuala Lumpur"],
        "Labuan": ["Labuan"],
        "Melaka": ["George Town", "Butterworth", "Bukit Mertajam", "Nibong Tebal", "Kepala Batas"],
        "Negeri Sembilan": ["Seremban", "Port Dickson", "Jempol", "Tampin", "Kuala Pilah", "Rembau", "Jelebu"],
        "Pahang": ["Kuantan", "Temerloh", "Bentong", "Pekan", "Rompin", "Raub", "Jerantut", "Lipis", "Cameron Highlands"],
        "Penang": ["George Town", "Butterworth", "Bukit Mertajam", "Seberang Perai"],
        "Perak": ["Ipoh", "Taiping", "Teluk Intan", "Manjung", "Kuala Kangsar", "Batu Gajah", "Kampar", "Tapah"],
        "Perlis": ["Kangar", "Arau", "Padang Besar"],
        "Putrajaya": ["Putrajaya"],
        "Sabah": ["Kota Kinabalu", "Sandakan", "Tawau", "Lahad Datu", "Keningau", "Kinabatangan", "Semporna"],
        "Sarawak": ["Kuching", "Miri", "Sibu", "Bintulu", "Limbang", "Samarahan", "Sri Aman"],
        "Selangor": ["Petaling Jaya", "Shah Alam", "Subang Jaya", "Klang", "Kajang", "Sepang", "Selayang", "Ampang", "Gombak", "Hulu Langat"],
        "Terengganu": ["Kuala Terengganu", "Kemaman", "Dungun", "Besut", "Marang", "Setiu"]
    };

    const stateSelect = document.getElementById('state_select');
    const citySelect = document.getElementById('city_select');

    if (stateSelect && citySelect) {
        const savedState = document.getElementById('saved_state') ? document.getElementById('saved_state').value : '';
        const savedCity = document.getElementById('saved_city') ? document.getElementById('saved_city').value : '';

        for (const state in locations) {
            let option = document.createElement('option');
            option.value = state;
            option.textContent = state;
            if (state === savedState) option.selected = true;
            stateSelect.appendChild(option);
        }

        function populateCities(selectedState, preselectCity = '') {
            citySelect.innerHTML = '<option value="">Select City...</option>';
            if (selectedState && locations[selectedState]) {
                const cities = locations[selectedState];
                cities.forEach(city => {
                    let option = document.createElement('option');
                    option.value = city;
                    option.textContent = city;
                    if (city === preselectCity) option.selected = true;
                    citySelect.appendChild(option);
                });
            }
        }

        if (savedState) {
            populateCities(savedState, savedCity);
        }

        stateSelect.addEventListener('change', function () {
            populateCities(this.value);
        });
    }

    const ajaxInput = document.getElementById('ajaxImageInput');
    const galleryGrid = document.getElementById('imageGalleryGrid');
    const carIdElement = document.getElementById('car_id_post');
    const carId = carIdElement ? carIdElement.value : '0';

    if (ajaxInput && galleryGrid) {
        ajaxInput.addEventListener('change', function () {
            if (this.files.length === 0) return;

            const formData = new FormData();
            formData.append('car_id_ajax', carId);
            for (let i = 0; i < this.files.length; i++) {
                formData.append('ajax_car_images[]', this.files[i]);
            }

            const uploadBtnSpan = document.querySelector('.custom-file-upload span');
            let originalText = '';
            if (uploadBtnSpan) {
                originalText = uploadBtnSpan.innerText;
                uploadBtnSpan.innerText = "Uploading... Please wait.";
                uploadBtnSpan.style.color = "#6366f1";
            }

            fetch('edit_car_details.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        data.images.forEach(img => {
                            const html = `
                        <div class="gallery-item" id="img_container_${img.id}">
                            <img src="${img.url}">
                            <button type="button" class="delete-img-btn" onclick="${carId == 0 ? `deleteTempImage('${img.id}')` : `deleteExistingImage(${img.id})`}" title="Delete image">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                        `;
                            galleryGrid.insertAdjacentHTML('beforeend', html);
                        });
                        if (uploadBtnSpan) {
                            uploadBtnSpan.innerText = originalText;
                            uploadBtnSpan.style.color = "";
                        }
                    } else {
                        alert('Upload failed: ' + (data.message || 'Unknown error'));
                    }
                    ajaxInput.value = '';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred during upload.');
                    if (uploadBtnSpan) {
                        uploadBtnSpan.innerText = originalText;
                        uploadBtnSpan.style.color = "";
                    }
                });
        });
    }

    const originSelect = document.getElementById('car_origin_select');
    const historyTabBtn = document.getElementById('tab-history-btn');
    const basicTabBtn = document.querySelector('button[data-target="tab-basic"]');

    function toggleHistoryTab() {
        if (!originSelect || !historyTabBtn) return;

        if (originSelect.value === 'New Car') {
            historyTabBtn.style.display = 'none';
            if (historyTabBtn.classList.contains('active') && basicTabBtn) {
                basicTabBtn.click();
            }
            originSelect.className = 'badge badge-new';
        } else {
            historyTabBtn.style.display = 'inline-block';
            originSelect.className = 'badge badge-used';
        }
    }

    toggleHistoryTab();
    if (originSelect) {
        originSelect.addEventListener('change', toggleHistoryTab);
    }
});

function deleteExistingImage(imgId) {
    if (!confirm('Are you sure you want to delete this image? It will be removed immediately from the server.')) return;

    const formData = new FormData();
    formData.append('action', 'delete_image');
    formData.append('img_id', imgId);

    fetch('edit_car_details.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const el = document.getElementById('img_container_' + imgId);
                if (el) el.remove();
            } else {
                alert('Delete failed.');
            }
        })
        .catch(error => console.error('Error:', error));
}

function deleteTempImage(tempId) {
    if (!confirm('Are you sure you want to delete this temporary image?')) return;

    const formData = new FormData();
    formData.append('action', 'delete_temp_image');
    formData.append('temp_id', tempId);

    fetch('edit_car_details.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                const el = document.getElementById('img_container_' + tempId);
                if (el) el.remove();
            } else {
                alert('Delete failed.');
            }
        })
        .catch(error => console.error('Error:', error));
}