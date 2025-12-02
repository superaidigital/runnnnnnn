<?php
// pages/registration.php
// หน้าฟอร์มสมัครเข้าร่วมกิจกรรม (Update: รวม Pay Later + Immediate Upload)

// --- 1. Validate and get Event Code ---
if (!isset($_GET['event_code']) || empty($_GET['event_code'])) {
    header('Location: index.php');
    exit;
}
$event_code = $_GET['event_code'];

// --- 2. Fetch event and distance data ---
$event_stmt = $mysqli->prepare("SELECT id, name, is_registration_open, payment_bank, payment_account_name, payment_account_number, payment_qr_code_url, start_date, enable_shipping, shipping_cost, payment_deadline FROM events WHERE event_code = ? LIMIT 1");
$event_stmt->bind_param("s", $event_code);
$event_stmt->execute();
$event_result = $event_stmt->get_result();
if ($event_result->num_rows === 0) {
    header('Location: index.php?page=home&error=event_not_found');
    exit;
}
$event = $event_result->fetch_assoc();
$event_stmt->close();

if (!$event['is_registration_open']) {
    header('Location: index.php?page=microsite&event_code=' . urlencode($event_code));
    exit;
}

$distances_stmt = $mysqli->prepare("SELECT id, name, price, category FROM distances WHERE event_id = ? ORDER BY price DESC");
$distances_stmt->bind_param("i", $event['id']);
$distances_stmt->execute();
$distances_result = $distances_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$distances_stmt->close();

// --- 3. Fetch Master Data ---
$master_titles = $mysqli->query("SELECT * FROM master_titles ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);
$master_shirt_sizes = $mysqli->query("SELECT * FROM master_shirt_sizes ORDER BY FIELD(name, 'XS', 'S', 'M', 'L', 'XL', '2XL', '3XL')")->fetch_all(MYSQLI_ASSOC);
$master_genders = $mysqli->query("SELECT * FROM master_genders ORDER BY id ASC")->fetch_all(MYSQLI_ASSOC);

// --- 4. Check for logged-in runner ---
$logged_in_runner_data = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_stmt = $mysqli->prepare("SELECT title, first_name, last_name, gender, birth_date, email, phone, line_id, thai_id, emergency_contact_name, emergency_contact_phone, disease, disease_detail, address FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $result = $user_stmt->get_result();
    if ($result->num_rows > 0) {
        $logged_in_runner_data = $result->fetch_assoc();
    }
    $user_stmt->close();
}

$page_title = 'สมัครเข้าร่วม: ' . e($event['name']);
?>

<div id="multi-step-form-container">
    <h2 class="text-3xl font-extrabold mb-6 text-gray-800">สมัคร: <?= e($event['name']) ?></h2>

    <div id="progress-bar-container" class="mb-8"></div>

    <!-- [SECURITY] CSRF Token hidden field for fetch API -->
    <input type="hidden" id="csrf_token_val" value="<?= generate_csrf_token() ?>">

    <form id="registration-form" onsubmit="return false;">
        <input type="hidden" name="event_id" value="<?= e($event['id']) ?>">
        <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
        
        <div id="form-step-content">
            <!-- JS will render content here -->
        </div>
    </form>

    <div class="flex justify-between mt-8">
        <button id="prev-btn" class="py-2 px-6 rounded-lg bg-gray-300 text-gray-800 hover:bg-gray-400 transition font-bold" onclick="prevStep()">
            <i class="fa-solid fa-chevron-left mr-2"></i> ย้อนกลับ
        </button>
        <button id="next-btn" class="py-2 px-6 rounded-lg bg-primary text-white hover:opacity-90 transition font-bold" onclick="nextStep()">
            ถัดไป <i class="fa-solid fa-chevron-right ml-2"></i>
        </button>
    </div>
</div>

<!-- Script Section -->
<script>
// --- Variable Definitions ---
let currentStep = 1;
const totalSteps = 3;
let registrationData = {};
const currentEvent = <?= json_encode($event, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const distances = <?= json_encode($distances_result, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const eventCode = '<?= e($event_code) ?>';
const loggedInRunner = <?= $logged_in_runner_data ? json_encode($logged_in_runner_data, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) : 'null' ?>;
const masterTitles = <?= json_encode($master_titles, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const masterShirtSizes = <?= json_encode($master_shirt_sizes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const masterGenders = <?= json_encode($master_genders, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// --- Helper Functions ---

function showMessage(title, text) {
    const box = document.getElementById('message-box');
    if (box) {
        document.getElementById('message-title').textContent = title;
        document.getElementById('message-text').textContent = text;
        document.getElementById('message-text').classList.remove('hidden');
        document.getElementById('message-content-container').classList.add('hidden');
        document.getElementById('message-action-btn').classList.add('hidden');
        box.classList.remove('hidden');
    } else {
        alert(`${title}\n\n${text}`);
    }
}

function renderProgressBar() {
    const registrationSteps = [
        { name: 'เลือกระยะทาง' },
        { name: 'กรอกข้อมูลส่วนตัว' },
        { name: 'สรุปและยืนยัน' }
    ];
    const container = document.getElementById('progress-bar-container');
    if (!container) return;
    container.innerHTML = `
        <div class="flex justify-between text-xs font-medium text-gray-500 mb-2">
            ${registrationSteps.map((s, index) => `<span class="${index + 1 === currentStep ? 'text-primary font-bold' : ''}">${s.name}</span>`).join('')}
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2.5">
            <div class="bg-primary h-2.5 rounded-full transition-all duration-500" style="width: ${((currentStep - 1) / (totalSteps - 1)) * 100}%"></div>
        </div>
    `;
}

function updateShippingOption(radio) {
    const addressContainer = document.getElementById('shipping-address-container');
    const addressTextarea = document.getElementById('shipping_address');
    if (!addressContainer || !addressTextarea) return;
    if (radio.value === 'จัดส่ง') {
        addressContainer.classList.remove('hidden');
        addressTextarea.required = true;
    } else {
        addressContainer.classList.add('hidden');
        addressTextarea.required = false;
    }
}

function handleDiseaseChange(event) {
    const diseaseDetailContainer = document.getElementById('disease-detail-container');
    const diseaseDetailTextarea = document.getElementById('disease_detail');
    if (!diseaseDetailContainer || !diseaseDetailTextarea) return;
    if (event.target.value === 'มีโรคประจำตัว') {
        diseaseDetailContainer.classList.remove('hidden');
        diseaseDetailTextarea.required = true;
    } else {
        diseaseDetailContainer.classList.add('hidden');
        diseaseDetailTextarea.required = false;
    }
}

// --- Validation Functions ---
function validateIdentity(id) {
    if (!id) return false;
    id = id.trim();
    if (/^\d{13}$/.test(id)) {
        let sum = 0;
        for (let i = 0; i < 12; i++) { sum += parseInt(id.charAt(i)) * (13 - i); }
        return parseInt(id.charAt(12)) === (11 - (sum % 11)) % 10;
    }
    if (/^[A-Za-z0-9]{6,20}$/.test(id)) {
        return true;
    }
    return false;
}

function e(str) {
    if (!str) return '';
    return str.toString().replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// --- Attach Listeners ---
function attachEventListeners() {
    if (currentStep === 2) {
        const diseaseRadios = document.querySelectorAll('input[name="disease"]');
        diseaseRadios.forEach(radio => radio.addEventListener('change', handleDiseaseChange));
        
        const shippingRadios = document.querySelectorAll('input[name="shipping_option"]');
        shippingRadios.forEach(radio => radio.addEventListener('change', () => updateShippingOption(radio)));
        
        const thaiIdInput = document.getElementById('thai_id');
        const thaiIdError = document.getElementById('thai-id-error');
        if (thaiIdInput && thaiIdError) {
            thaiIdInput.addEventListener('blur', () => {
                const isValid = validateIdentity(thaiIdInput.value);
                thaiIdError.classList.toggle('hidden', !thaiIdInput.value || isValid);
                if (!isValid && thaiIdInput.value) {
                    thaiIdError.textContent = "รูปแบบเลขไม่ถูกต้อง (ต้องเป็นเลขบัตรฯ 13 หลัก หรือ Passport)";
                }
            });
        }

        if (typeof flatpickr !== 'undefined') {
            flatpickr(".datepicker-thai", {
                locale: "th",
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "j F Y",
                defaultDate: document.querySelector('input[name="birth_date"]').value || null,
                disableMobile: "true",
                onReady: function(selectedDates, dateStr, instance) {
                    const yearInput = instance.currentYearElement;
                    if (yearInput) yearInput.value = parseInt(yearInput.value) + 543;
                },
                onValueUpdate: function(selectedDates, dateStr, instance) {
                    if (selectedDates[0]) {
                        const date = selectedDates[0];
                        const day = date.getDate();
                        const month = instance.l10n.months.longhand[date.getMonth()];
                        const yearBE = date.getFullYear() + 543;
                        instance.altInput.value = `${day} ${month} ${yearBE}`;
                    }
                },
                onYearChange: function(selectedDates, dateStr, instance) {
                     const yearInput = instance.currentYearElement;
                     setTimeout(() => { yearInput.value = parseInt(instance.currentYear) + 543; }, 10);
                }
            });
        }
    }
}

// --- Render Logic ---
function renderCurrentStep() {
    renderProgressBar();
    const content = document.getElementById('form-step-content');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');

    if (!content || !prevBtn || !nextBtn) return;
    content.innerHTML = '';
    prevBtn.style.display = currentStep > 1 ? 'inline-block' : 'none';
    const payLaterEnabled = currentEvent.payment_deadline != null;
    
    // ปรับข้อความปุ่มในขั้นตอนสุดท้าย
    if (currentStep === totalSteps) {
        nextBtn.innerHTML = `<i class="fa-solid fa-check-circle mr-2"></i> ยืนยันการสมัคร`;
    } else {
        nextBtn.innerHTML = `ถัดไป <i class="fa-solid fa-chevron-right ml-2"></i>`;
    }
    nextBtn.disabled = false;

    if (currentStep === 1) {
        content.innerHTML = `
            <div class="space-y-4">
                <h3 class="text-xl font-semibold mb-4">1. เลือกระยะทางการแข่งขัน</h3>
                ${distances.map(d => `
                    <label class="flex items-center p-4 border rounded-lg cursor-pointer transition duration-200 hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-blue-50">
                        <input type="radio" name="distance_id" value="${d.id}" data-distance-name="${e(d.name)}" class="h-5 w-5 text-primary focus:ring-primary" ${registrationData.distance_id == d.id ? 'checked' : ''}>
                        <div class="ml-4 flex justify-between w-full items-center">
                            <span class="font-medium text-gray-900 text-lg">${e(d.name)} (${e(d.category)})</span>
                            <span class="font-bold text-primary text-lg">${parseFloat(d.price).toLocaleString('th-TH')} บาท</span>
                        </div>
                    </label>
                `).join('') || '<p class="text-gray-500">ไม่มีระยะทางให้เลือกสำหรับกิจกรรมนี้</p>'}
            </div>
        `;
    } else if (currentStep === 2) {
        const userInfo = registrationData.userInfo || loggedInRunner || {};
        const getUserInfoValue = key => userInfo[key] || '';
        
        const titleOptions = masterTitles.map(t => `<option value="${e(t.name)}" ${userInfo.title === t.name ? 'selected' : ''}>${e(t.name)}</option>`).join('');
        const shirtSizeOptions = masterShirtSizes.map(s => `<option value="${e(s.name)}" ${userInfo.shirt_size === s.name ? 'selected' : ''}>${e(s.name)} ${e(s.description)}</option>`).join('');
        const genderOptions = masterGenders.map(g => `<option value="${e(g.name)}" ${userInfo.gender === g.name ? 'selected' : ''}>${e(g.name)}</option>`).join('');

        let shippingHtml = '';
        if (currentEvent.enable_shipping == 1) {
             const shippingCost = parseFloat(currentEvent.shipping_cost || 0);
             const isShippingSelected = getUserInfoValue('shipping_option') === 'จัดส่ง';
             const isSelfPickupSelected = !isShippingSelected;
             shippingHtml = `
                <div class="border-t pt-4">
                    <h4 class="text-md font-semibold text-gray-800 mb-2">เลือกวิธีรับอุปกรณ์ (Race Kit)</h4>
                    <div class="space-y-3">
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-blue-50">
                            <input type="radio" name="shipping_option" value="รับเอง" class="h-4 w-4 text-primary" ${isSelfPickupSelected ? 'checked' : ''}>
                            <span class="ml-3 font-medium text-gray-900">รับด้วยตนเอง (หน้างาน)</span>
                            <span class="ml-auto font-bold text-primary">ฟรี</span>
                        </label>
                        <label class="flex items-center p-3 border rounded-lg cursor-pointer hover:border-primary has-[:checked]:border-primary has-[:checked]:bg-blue-50">
                            <input type="radio" name="shipping_option" value="จัดส่ง" class="h-4 w-4 text-primary" ${isShippingSelected ? 'checked' : ''}>
                            <span class="ml-3 font-medium text-gray-900">จัดส่งทางไปรษณีย์</span>
                            <span class="ml-auto font-bold text-primary">+${shippingCost.toLocaleString('th-TH')} บาท</span>
                        </label>
                    </div>
                    <div id="shipping-address-container" class="mt-4 ${isShippingSelected ? '' : 'hidden'}">
                        <label for="shipping_address" class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่สำหรับจัดส่ง <span class="text-red-500">*</span></label>
                        <textarea id="shipping_address" name="shipping_address" rows="4" class="w-full p-2 border border-gray-300 rounded-md shadow-sm">${e(getUserInfoValue('shipping_address') || getUserInfoValue('address'))}</textarea>
                    </div>
                </div>
             `;
        } else {
             shippingHtml = `<input type="hidden" name="shipping_option" value="รับเอง">`;
        }
        
        content.innerHTML = `
            <h3 class="text-xl font-semibold mb-4">2. ข้อมูลส่วนตัว</h3>
            <div class="p-6 bg-white rounded-xl border border-gray-200 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700">คำนำหน้า *</label><select name="title" class="w-full p-2 border rounded-md">${titleOptions}</select></div>
                    <div><label class="block text-sm font-medium text-gray-700">ชื่อจริง *</label><input type="text" name="first_name" value="${e(getUserInfoValue('first_name'))}" class="w-full p-2 border rounded-md"></div>
                    <div><label class="block text-sm font-medium text-gray-700">นามสกุล *</label><input type="text" name="last_name" value="${e(getUserInfoValue('last_name'))}" class="w-full p-2 border rounded-md"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700">เพศ *</label><select name="gender" class="w-full p-2 border rounded-md"><option value="">-- เลือก --</option>${genderOptions}</select></div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">วัน/เดือน/ปีเกิด (พ.ศ.) *</label>
                        <input type="text" name="birth_date" id="birth_date" value="${e(getUserInfoValue('birth_date'))}" class="w-full p-2 border rounded-md datepicker-thai bg-white" placeholder="เลือกวันเกิด" readonly>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">เลขบัตรประชาชน / Passport *</label>
                        <input type="text" id="thai_id" name="thai_id" value="${e(getUserInfoValue('thai_id'))}" class="w-full p-2 border rounded-md">
                        <p id="thai-id-error" class="text-xs text-red-500 mt-1 hidden">รูปแบบเลขไม่ถูกต้อง</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div><label class="block text-sm font-medium text-gray-700">อีเมล *</label><input type="email" name="email" value="${e(getUserInfoValue('email'))}" class="w-full p-2 border rounded-md"></div>
                    <div><label class="block text-sm font-medium text-gray-700">เบอร์โทร *</label><input type="tel" name="phone" value="${e(getUserInfoValue('phone'))}" class="w-full p-2 border rounded-md"></div>
                </div>
                <div class="border-t pt-4">
                    <h4 class="font-semibold mb-2">ติดต่อฉุกเฉิน</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><label class="block text-sm font-medium text-gray-700">ชื่อผู้ติดต่อ *</label><input type="text" name="emergency_contact_name" value="${e(getUserInfoValue('emergency_contact_name'))}" class="w-full p-2 border rounded-md"></div>
                        <div><label class="block text-sm font-medium text-gray-700">เบอร์โทร *</label><input type="tel" name="emergency_contact_phone" value="${e(getUserInfoValue('emergency_contact_phone'))}" class="w-full p-2 border rounded-md"></div>
                    </div>
                </div>
                <div class="p-4 bg-red-50 border border-red-200 rounded-md">
                    <label class="block text-sm font-medium text-red-600 mb-2">ข้อมูลการแพทย์</label>
                    <div class="flex space-x-4">
                        <label><input type="radio" name="disease" value="ไม่มีโรคประจำตัว" ${getUserInfoValue('disease') !== 'มีโรคประจำตัว' ? 'checked' : ''}> ไม่มี</label>
                        <label><input type="radio" name="disease" value="มีโรคประจำตัว" ${getUserInfoValue('disease') === 'มีโรคประจำตัว' ? 'checked' : ''}> มีโรคประจำตัว</label>
                    </div>
                    <div id="disease-detail-container" class="mt-2 ${getUserInfoValue('disease') !== 'มีโรคประจำตัว' ? 'hidden' : ''}">
                        <textarea id="disease_detail" name="disease_detail" class="w-full p-2 border rounded-md" placeholder="ระบุรายละเอียด...">${e(getUserInfoValue('disease_detail'))}</textarea>
                    </div>
                </div>
                <div><label class="block text-sm font-medium text-gray-700">ไซส์เสื้อ *</label><select name="shirt_size" class="w-full p-2 border rounded-md"><option value="">-- เลือก --</option>${shirtSizeOptions}</select></div>
                ${shippingHtml}
            </div>
        `;
    } else if (currentStep === 3) {
        const selectedDistance = distances.find(d => d.id == registrationData.distance_id);
        const userInfo = registrationData.userInfo;
        const racePrice = parseFloat(selectedDistance?.price || 0);
        const shippingCost = (userInfo.shipping_option === 'จัดส่ง' && currentEvent.enable_shipping == 1) ? parseFloat(currentEvent.shipping_cost || 0) : 0;
        const totalPrice = racePrice + shippingCost;
        registrationData.total_amount = totalPrice;

        // [MODIFIED] Payment UI Logic
        // แสดงทั้ง QR Code และช่องอัปโหลดสลิปเสมอ
        // หาก payLaterEnabled = true จะเป็น Optional
        let paymentHtml = `
            <h3 class="text-xl font-semibold my-4 text-gray-800">การชำระเงิน</h3>
            <div class="bg-white p-6 border rounded-xl shadow-sm text-center space-y-4">
                
                <div class="flex flex-col items-center">
                    <p class="font-bold text-lg mb-2">สแกน QR Code เพื่อชำระเงิน</p>
                    <img src="${e(currentEvent.payment_qr_code_url)}" class="w-48 h-48 object-contain bg-white border p-2 rounded-lg shadow-sm">
                    <p class="text-primary font-bold text-xl mt-2">${totalPrice.toLocaleString()} บาท</p>
                </div>

                <div class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg text-left inline-block w-full">
                    <p><strong>ธนาคาร:</strong> ${e(currentEvent.payment_bank)}</p>
                    <p><strong>ชื่อบัญชี:</strong> ${e(currentEvent.payment_account_name)}</p>
                    <p><strong>เลขที่บัญชี:</strong> ${e(currentEvent.payment_account_number)}</p>
                </div>

                <div class="text-left border-t pt-4">
                    <label class="block text-sm font-medium mb-2 text-gray-700">
                        หลักฐานการโอนเงิน (สลิป) 
                        ${payLaterEnabled 
                            ? '<span class="text-gray-500 font-normal bg-green-100 text-green-800 px-2 py-0.5 rounded-full text-xs ml-2">แนบภายหลังได้</span>' 
                            : '<span class="text-red-500 font-bold">* จำเป็น</span>'}
                    </label>
                    <input type="file" id="payment_slip" name="payment_slip" accept="image/*,application/pdf" class="w-full text-sm text-gray-500
                        file:mr-4 file:py-2 file:px-4
                        file:rounded-full file:border-0
                        file:text-sm file:font-semibold
                        file:bg-blue-50 file:text-blue-700
                        hover:file:bg-blue-100
                    ">
                    ${payLaterEnabled ? '<p class="text-xs text-gray-500 mt-2"><i class="fa-solid fa-info-circle text-blue-500"></i> หากท่านยังไม่สะดวกชำระเงินในขณะนี้ สามารถกดปุ่ม <strong>"ยืนยันการสมัคร"</strong> เพื่อบันทึกข้อมูลก่อน แล้วค่อยกลับมาแนบสลิปผ่านทางหน้าแดชบอร์ดภายหลังได้</p>' : ''}
                </div>
            </div>`;

        content.innerHTML = `
            <h3 class="text-xl font-semibold mb-4">3. สรุปข้อมูล</h3>
            <div class="p-6 bg-gray-50 border rounded-xl space-y-2 text-sm text-gray-800">
                <p><strong>ชื่อ-สกุล:</strong> ${e(userInfo.first_name)} ${e(userInfo.last_name)}</p>
                <p><strong>ระยะทาง:</strong> ${e(selectedDistance?.name)} (${racePrice.toLocaleString()} บาท)</p>
                <p><strong>การรับของ:</strong> ${e(userInfo.shipping_option)} (${shippingCost.toLocaleString()} บาท)</p>
                ${userInfo.shipping_option === 'จัดส่ง' ? `<p><strong>ที่อยู่:</strong> ${e(userInfo.shipping_address)}</p>` : ''}
                <div class="pt-2 border-t font-bold text-lg text-primary">ยอดรวมสุทธิ: ${totalPrice.toLocaleString()} บาท</div>
            </div>
            ${paymentHtml}
        `;
    }
    attachEventListeners();
}

function nextStep() {
    const form = document.getElementById('registration-form');
    if (currentStep === 1) {
        if (!form.querySelector('input[name="distance_id"]:checked')) {
            showMessage('แจ้งเตือน', 'กรุณาเลือกระยะทาง'); return;
        }
        const dist = form.querySelector('input[name="distance_id"]:checked');
        registrationData.distance_id = dist.value;
        registrationData.distance_name = dist.dataset.distanceName;
    } else if (currentStep === 2) {
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        for (let input of inputs) {
            if (!input.value.trim()) {
                showMessage('แจ้งเตือน', 'กรุณากรอกข้อมูลให้ครบถ้วน'); input.focus(); return;
            }
        }
        
        if(!validateIdentity(form.querySelector('#thai_id').value)) {
            showMessage('แจ้งเตือน', 'เลขบัตรประชาชนหรือ Passport ไม่ถูกต้อง'); return;
        }
        
        const formData = new FormData(form);
        registrationData.userInfo = Object.fromEntries(formData.entries());
    }

    if (currentStep < totalSteps) {
        currentStep++;
        renderCurrentStep();
        window.scrollTo(0,0);
    } else {
        completeRegistration();
    }
}

function prevStep() {
    if (currentStep > 1) currentStep--;
    renderCurrentStep();
    window.scrollTo(0,0);
}

function completeRegistration() {
    const payLaterEnabled = currentEvent.payment_deadline != null;
    const slipInput = document.getElementById('payment_slip');
    let slipFile = null;

    if (slipInput && slipInput.files[0]) {
        slipFile = slipInput.files[0];
    }

    // Logic: 
    // 1. ถ้าไม่ได้เปิด Pay Later -> ต้องมีสลิป
    // 2. ถ้าเปิด Pay Later -> มีสลิปก็ได้ (สถานะรอตรวจสอบ) หรือไม่มีสลิปก็ได้ (สถานะรอชำระเงิน)
    if (!payLaterEnabled && !slipFile) {
        showMessage('แจ้งเตือน', 'กรุณาอัปโหลดหลักฐานการโอนเงิน'); return;
    }

    const formData = new FormData();
    formData.append('event_id', currentEvent.id);
    formData.append('event_code', eventCode);
    formData.append('distance_id', registrationData.distance_id);
    formData.append('distance_name', registrationData.distance_name);
    formData.append('total_amount', registrationData.total_amount);
    
    const csrfToken = document.getElementById('csrf_token_val').value;
    formData.append('csrf_token', csrfToken);

    for (const key in registrationData.userInfo) {
        formData.append(key, registrationData.userInfo[key]);
    }

    if (slipFile) formData.append('payment_slip', slipFile);

    const nextBtn = document.getElementById('next-btn');
    nextBtn.disabled = true;
    nextBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> กำลังบันทึก...';

    fetch('actions/process_registration.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.href = data.redirect_url;
        } else {
            showMessage('เกิดข้อผิดพลาด', data.message);
            nextBtn.disabled = false;
            nextBtn.innerHTML = 'ยืนยันการสมัคร';
        }
    })
    .catch(err => {
        showMessage('Error', 'การเชื่อมต่อขัดข้อง');
        console.error(err);
        nextBtn.disabled = false;
        nextBtn.innerHTML = 'ยืนยันการสมัคร';
    });
}

document.addEventListener('DOMContentLoaded', () => {
    if (loggedInRunner) {
        registrationData.userInfo = { ...loggedInRunner };
        if (loggedInRunner.address) registrationData.userInfo.shipping_address = loggedInRunner.address;
    }
    renderCurrentStep();
});
</script>