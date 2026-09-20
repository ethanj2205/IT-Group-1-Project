<?php
require_once __DIR__ . '/auth_common.php';
$patient = require_patient_page();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediQueue SA - Patient Dashboard</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="app-header">
  <div class="header-inner">
    <div class="brand">
      <div class="brand-mark"><?= htmlspecialchars(strtoupper(substr($patient["full_name"], 0, 1))) ?></div>
      <div><h1>MediQueue SA</h1><p>Patient Dashboard · Durban</p></div>
    </div>
    <div class="header-actions">
      <span class="live-pill">● LIVE</span>
      <a class="logout" href="logout.php">Logout</a>
    </div>
  </div>
</header>

<main>
<section class="card">
  <div class="dashboard-title">
    <div>
      <div class="eyebrow">Patient portal</div>
      <h2>Good day, <?= htmlspecialchars($patient["full_name"]) ?></h2>
      <p style="margin:4px 0 0">Your virtual care dashboard — queue, triage, prescriptions and referrals in one place.</p>
    </div>
    <div class="avatar"><?= htmlspecialchars(strtoupper(substr($patient["full_name"], 0, 1))) ?></div>
  </div>

  <div class="queue-hero">
    <div class="queue-position">
      <div class="label">QUEUE POSITION</div>
      <div class="number" id="patientQueuePosition">#—</div>
      <div class="muted">Your live virtual queue</div>
    </div>
    <div class="wait-badge"><strong id="patientEstimatedWait">—</strong><span>estimated wait</span></div>
  </div>

  <div class="section-label">AI Triage Result</div>
  <div class="triage-box" id="triageSummary">
    <div class="muted">Submit your symptoms to receive an urgency assessment.</div>
  </div>

  <div class="section-label">Quick Actions</div>
  <div class="quick-actions">
    <button onclick="document.getElementById('symptoms').focus()">🩺 Check Symptoms</button>
    <button onclick="joinQueue()">▣ Join Consult</button>
    <button onclick="showPrescriptions()">💊 Prescriptions</button>
  </div>
  <div class="featureButtons">
    <button onclick="showRecords()">▤ My Records</button>
    <button onclick="startLiveGPS()">📍 Live GPS</button>
    <button onclick="loadQueue()">↻ Refresh Queue</button>
  </div>
</section>

<section class="card">
  <div class="dashboard-title">
    <div><div class="eyebrow">Consultation setup</div><h2>Choose your care</h2></div>
  </div>

  <div class="patient-meta">
    <div class="meta-item"><strong>NAME</strong><span><?= htmlspecialchars($patient["full_name"]) ?></span></div>
    <div class="meta-item"><strong>CONTACT</strong><span><?= htmlspecialchars($patient["contact_number"] ?: "Not provided") ?></span></div>
  </div>

  <input id="patientId" type="hidden" value="<?= (int)$patient["id"] ?>">

  <label>Hospital</label>
  <select id="hospitalId" onchange="loadHospitalDoctors()"><option value="">Loading hospitals...</option></select>

  <label>Preferred Doctor</label>
  <select id="doctorId"><option value="">Choose a hospital first</option></select>
  <small class="formHint">Choose your preferred doctor. If another doctor needs to assist, the clinical team can call you from the same hospital queue.</small>

  <label>Symptoms</label>
  <textarea id="symptoms" placeholder="Describe your symptoms..."></textarea>
  <button class="primary" onclick="submitTriage()">Assess Symptoms</button>
  <div id="triageResult"></div>
  <button id="joinButton" class="secondary" onclick="joinQueue()" disabled>➕ Join Virtual Queue</button>
  <div id="patientStatus"></div>
</section>

<section class="card full">
  <div class="queueHeader">
    <div><div class="eyebrow">Live service</div><h2>Virtual Queue</h2><p>Updates automatically every 2 seconds.</p></div>
    <span class="live-pill">● LIVE</span>
  </div>
  <div id="queue">Loading queue...</div>
</section>

<section class="card full">
  <div class="section-label">Location & referrals</div>
  <div class="location-box">
    <label style="margin-top:0">Live GPS Location</label>
    <input id="patientLocation" type="text" placeholder="Waiting for GPS..." readonly>
    <div id="patientLocationStatus"><div class="empty">📍 Live GPS is off.</div></div>
  </div>
  <div id="referralHospitals" class="featurePanel"></div>
</section>

<section class="card full">
  <div class="section-label">Health records</div>
  <div id="patientRecords" class="featurePanel"></div>
  <div id="patientCalendar" class="featurePanel"></div>
  <div id="medicationAlarms" class="featurePanel"></div>
</section>
<script>




let currentTriageId = null;

let currentConsultationQueueId = null;


/*
|--------------------------------------------------------------------------
| API helper
|--------------------------------------------------------------------------
*/

async function api(route, method = "GET", data = null)
{
    const url = "api.php?route=" + route;

    const options = {
        method,
        headers: {
            "Accept": "application/json"
        }
    };

    if (data !== null) {
        options.headers["Content-Type"] = "application/json";
        options.body = JSON.stringify(data);
    }

    const response = await fetch(url, options);
    const raw = await response.text();

    let result;

    try {
        result = raw ? JSON.parse(raw) : {};
    } catch (parseError) {
        console.error("API returned non-JSON data:", raw);
        throw new Error(
            "The server returned an invalid response. Check PHP/MySQL for an error."
        );
    }

    if (!response.ok) {
        throw new Error(
            result.error || "Request failed (HTTP " + response.status + ")"
        );
    }

    return result;
}

/*
|--------------------------------------------------------------------------
| Hospital and doctor selection
|--------------------------------------------------------------------------
*/
async function loadHospitals()
{
    const hospitalSelect = document.getElementById("hospitalId");
    try {
        const result = await api("hospitals");
        hospitalSelect.innerHTML = `<option value="">Choose a hospital</option>` +
            (result.hospitals || []).map(h =>
                `<option value="${Number(h.id)}">${escapeHtml(h.name)} — ${escapeHtml(h.city)}</option>`
            ).join("");

        if (result.hospitals && result.hospitals.length) {
            hospitalSelect.value = String(result.hospitals[0].id);
            await loadHospitalDoctors();
            loadQueue();
        }
    } catch (error) {
        hospitalSelect.innerHTML = `<option value="">Could not load hospitals</option>`;
        document.getElementById("patientStatus").innerHTML =
            `<div class="error">${escapeHtml(error.message)}</div>`;
    }
}

async function loadHospitalDoctors()
{
    const hospitalId = document.getElementById("hospitalId").value;
    const doctorSelect = document.getElementById("doctorId");

    if (!hospitalId) {
        doctorSelect.innerHTML = `<option value="">Choose a hospital first</option>`;
        return;
    }

    doctorSelect.innerHTML = `<option value="">Loading doctors...</option>`;

    try {
        const result = await api(
            "hospital_doctors&hospital_id=" + encodeURIComponent(hospitalId)
        );

        const doctors = result.doctors || [];
        doctorSelect.innerHTML =
            `<option value="">Choose your preferred doctor</option>` +
            doctors.map(d => `
                <option value="${Number(d.id)}" ${Number(d.available) ? "" : "disabled"}>
                    ${escapeHtml(d.full_name)}${Number(d.available) ? " — Available" : " — Unavailable"}
                </option>
            `).join("");

        if (doctors.some(d => Number(d.available))) {
            const first = doctors.find(d => Number(d.available));
            doctorSelect.value = String(first.id);
        }
    } catch (error) {
        doctorSelect.innerHTML = `<option value="">Could not load doctors</option>`;
    }
}

/*
|--------------------------------------------------------------------------
| Submit triage
|--------------------------------------------------------------------------
*/

async function submitTriage()
{

    const patientId =
        document
        .getElementById(
            "patientId"
        )
        .value;


    const symptoms =
        document
        .getElementById(
            "symptoms"
        )
        .value;


    if (!patientId) {

        alert(
            "Enter a Patient ID."
        );

        return;
    }


    if (!symptoms.trim()) {

        alert(
            "Enter at least one symptom."
        );

        return;
    }


    try {

        const result =
            await api(
                "triage",
                "POST",
                {
                    patient_id:
                        patientId,

                    symptoms:
                        symptoms
                }
            );


        currentTriageId =
            result.triage_id;


        document
        .getElementById(
            "triageResult"
        )
        .innerHTML = `

            <div class="result">

                <strong>
                    Triage Complete
                </strong>

                <span>
                    ${result.urgency}
                </span>

                ${result.confidence !== null && result.confidence !== undefined ? `
                <small>
                    Confidence:
                    ${(result.confidence * 100).toFixed(0)}%
                </small>
                ` : ""}

                <small>
                    Triage ID:
                    ${result.triage_id}
                </small>

            </div>

        `;


        document
        .getElementById(
            "joinButton"
        )
        .disabled = false;

    }

    catch (error) {

        alert(
            error.message
        );
    }
}


/*
|--------------------------------------------------------------------------
| Join queue
|--------------------------------------------------------------------------
*/

async function joinQueue()
{

    const patientId =
        document
        .getElementById(
            "patientId"
        )
        .value;


    const hospitalId =
        document
        .getElementById(
            "hospitalId"
        )
        .value;


    const doctorId = document.getElementById("doctorId").value;

    if (!currentTriageId) {

        alert(
            "Submit triage first."
        );

        return;
    }

    if (!hospitalId) {
        alert("Choose a hospital first.");
        return;
    }

    if (!doctorId) {
        alert("Choose a doctor first.");
        return;
    }


    try {

        const result =
            await api(
                "queue_join",
                "POST",
                {
                    patient_id:
                        patientId,

                    triage_id:
                        currentTriageId,

                    hospital_id:
                        hospitalId,

                    doctor_id:
                        document.getElementById("doctorId").value
                }
            );


        document
        .getElementById(
            "patientStatus"
        )
        .innerHTML = `

            <div class="success">

                Successfully joined queue.

                <br>

                Queue Number:

                <strong>
                    ${result.queue_number}
                </strong>

                <br>

                Doctor:

                <strong>
                    ${escapeHtml(result.doctor_name || "Assigned doctor")}
                </strong>

            </div>

        `;


        loadQueue();

        if (window.advanceToNextPatient) {
            window.advanceToNextPatient();
        }

    }

    catch (error) {

        alert(
            error.message
        );
    }
}



/*
|--------------------------------------------------------------------------
| Patient records
|--------------------------------------------------------------------------
*/
async function showRecords()
{
    const patientId = document.getElementById("patientId").value;
    const panel = document.getElementById("patientRecords");
    panel.innerHTML = "Loading previous consultations...";

    try {
        const result = await api("patient_records&patient_id=" + encodeURIComponent(patientId));
        if (!result.records.length) {
            panel.innerHTML = `<div class="empty">No previous consultation sessions found.</div>`;
            return;
        }

        panel.innerHTML = `
            <h3>📋 Previous Consultation Sessions</h3>
            ${result.records.map(r => `
                <div class="recordCard">
                    <strong>${escapeHtml(r.doctor_name)}</strong>
                    <span>${escapeHtml(r.created_at)}</span>
                    <span>Outcome: ${escapeHtml(r.outcome)}</span>
                    <span>Urgency: ${escapeHtml(r.urgency)}</span>
                    <span>Symptoms: ${escapeHtml(r.symptoms || "-")}</span>
                    <span>Notes: ${escapeHtml(r.notes || "-")}</span>
                </div>
            `).join("")}
        `;
    } catch (error) {
        panel.innerHTML = `<div class="error">${escapeHtml(error.message)}</div>`;
    }
}

async function showPrescriptions()
{
    const patientId = document.getElementById("patientId").value;
    const panel = document.getElementById("patientRecords");
    panel.innerHTML = "Loading prescriptions...";

    try {
        const result = await api("prescriptions&patient_id=" + encodeURIComponent(patientId));
        if (!result.prescriptions.length) {
            panel.innerHTML = `<div class="empty">No prescriptions have been issued yet.</div>`;
            document.getElementById("patientCalendar").innerHTML = "";
            document.getElementById("medicationAlarms").innerHTML = "";
            return;
        }

        panel.innerHTML = `
            <h3>💊 My Prescriptions</h3>
            <p class="muted">Follow the doctor's instructions. The reminder is an aid and does not replace medical advice.</p>
            ${result.prescriptions.map(p => `
                <div class="recordCard prescriptionCard">
                    <strong>💊 ${escapeHtml(p.medication)}</strong>
                    <span>Doctor: ${escapeHtml(p.doctor_name)}</span>
                    <span>📝 Description: ${escapeHtml(p.instructions || "-")}</span>
                    <span>💉 Dose: ${escapeHtml(p.dose_amount || "-")} ${escapeHtml(p.dose_unit || "")}</span>
                    <span>📦 Quantity: ${escapeHtml(p.quantity ?? "-")} ${escapeHtml(p.quantity_unit || "")}</span>
                    <span>⏱️ Duration: ${escapeHtml(p.duration_value ?? "-")} ${escapeHtml(p.duration_unit || "")}</span>
                    <span>🔁 Frequency: ${escapeHtml(p.times_per_day ?? "-")} time(s) per day</span>
                    <span>⏰ Take at: ${escapeHtml(p.medication_times || "See doctor's instructions")}</span>
                    <span>📅 Treatment: ${escapeHtml(p.start_date || "-")} to ${escapeHtml(p.end_date || "-")}</span>
                    ${Number(p.refill_required) ? `<span>🔄 Refill return: <strong>${escapeHtml(p.refill_date || "Date to be confirmed")}</strong></span>` : ""}
                    ${Number(p.checkup_required) ? `<span>🩺 Check-up return: <strong>${escapeHtml(p.checkup_date || "Date to be confirmed")}</strong></span>` : ""}
                    ${p.followup_notes ? `<span>📌 Follow-up notes: ${escapeHtml(p.followup_notes)}</span>` : ""}
                </div>
            `).join("")}
        `;
        renderPatientCalendar(result.prescriptions);
        setupMedicationAlarms(result.prescriptions);
    } catch (error) {
        panel.innerHTML = `<div class="error">${escapeHtml(error.message)}</div>`;
    }
}

function parseTimes(value) {
    return String(value || "").split(",").map(x => x.trim()).filter(x => /^([01]\d|2[0-3]):[0-5]\d$/.test(x));
}

function renderPatientCalendar(prescriptions) {
    const dates = {};
    prescriptions.forEach(p => {
        [p.refill_date, p.checkup_date].forEach((d, i) => {
            if (d) dates[d] = dates[d] || [];
            if (d) dates[d].push(i === 0 ? "💊 Refill" : "🩺 Check-up");
        });
    });
    const days = Object.keys(dates).sort();
    const panel = document.getElementById("patientCalendar");
    panel.innerHTML = `<h3>📅 My Return Calendar</h3>${days.length ? days.map(d => `<div class="calendarEvent"><strong>${escapeHtml(d)}</strong><span>${escapeHtml(dates[d].join(" • "))}</span></div>`).join("") : `<div class="empty">No refill or check-up dates have been scheduled.</div>`}`;
}

let medicationAlarmTimer = null;
function setupMedicationAlarms(prescriptions) {
    const panel = document.getElementById("medicationAlarms");
    const alarms = [];
    prescriptions.forEach(p => parseTimes(p.medication_times).forEach(time => alarms.push({ medication:p.medication, time, start:p.start_date, end:p.end_date })));
    panel.innerHTML = `<h3>⏰ Medication Alarm Clock</h3>${alarms.length ? `<p>Reminders are based on the times entered by your doctor. Keep this dashboard open for the in-page alarm.</p>${alarms.map(a => `<div class="alarmRow"><strong>${escapeHtml(a.time)}</strong><span>${escapeHtml(a.medication)}</span></div>`).join("")}<button class="secondary" onclick="enableMedicationNotifications()">🔔 Enable Browser Notifications</button>` : `<div class="empty">No medication times were entered by the doctor.</div>`}`;
    if (medicationAlarmTimer) clearInterval(medicationAlarmTimer);
    medicationAlarmTimer = setInterval(() => checkMedicationAlarms(alarms), 15000);
    checkMedicationAlarms(alarms);
}

async function enableMedicationNotifications() {
    if (!("Notification" in window)) return alert("This browser does not support notifications.");
    const permission = await Notification.requestPermission();
    alert(permission === "granted" ? "Medication notifications enabled." : "Notifications were not enabled.");
}

function checkMedicationAlarms(alarms) {
    const now = new Date();
    const hhmm = String(now.getHours()).padStart(2,"0") + ":" + String(now.getMinutes()).padStart(2,"0");
    const today = now.toISOString().slice(0,10);
    alarms.filter(a => a.time === hhmm && (!a.start || today >= a.start) && (!a.end || today <= a.end)).forEach(a => {
        const key = `mq-alarm-${today}-${a.time}-${a.medication}`;
        if (localStorage.getItem(key)) return;
        localStorage.setItem(key, "1");
        alert(`⏰ Medication reminder: ${a.medication}\nTime: ${a.time}`);
        if ("Notification" in window && Notification.permission === "granted") new Notification("MediQueue medication reminder", {body:`It is ${a.time}. Take ${a.medication} according to your doctor's prescription.`});
    });
}

let gpsWatchId = null;
let lastGpsSentAt = 0;

async function loadReferralHospitals(queueId)
{
    const panel = document.getElementById("referralHospitals");
    if (!panel || !queueId) return;

    panel.innerHTML = `<div class="empty">🏥 Loading nearby Durban referral hospitals...</div>`;

    try {
        const result = await api(
            "referral_hospitals&queue_id=" + encodeURIComponent(queueId)
        );

        if (!result.gps_available) {
            panel.innerHTML = `
                <div class="empty">
                    🏥 Hospital referral recorded.<br>
                    Turn on Live GPS so MediQueue can suggest the nearest Durban hospitals.
                    <br><button class="secondary" onclick="startLiveGPS()">📍 Start Live GPS</button>
                </div>`;
            return;
        }

        if (!result.hospitals || !result.hospitals.length) {
            panel.innerHTML = `
                <div class="empty">
                    🏥 Hospital referral recorded, but no configured Durban referral hospital
                    was found within 60 km of your live GPS position.
                </div>`;
            return;
        }

        panel.innerHTML = `
            <h3>🏥 Choose a Hospital Near You</h3>
            <p>Based on your live GPS place. Hospitals are sorted from closest to furthest. You choose the hospital — MediQueue does not choose it for you.</p>
            ${result.hospitals.map((h, index) => `
                <div class="recordCard">
                    <strong>${index + 1}. ${escapeHtml(h.name)}</strong>
                    <span>📍 ${escapeHtml(h.city)}</span>
                    <span>📏 ${escapeHtml(h.distance_km)} km away from your current location</span>
                    <button class="secondary" onclick="chooseReferralHospital(${Number(h.id)}, '${escapeHtml(h.name).replace(/'/g, "\\'")}', ${Number(queueId)})">
                        🏥 Choose this hospital
                    </button>
                    <a href="${escapeHtml(h.directions_url)}" target="_blank" rel="noopener">
                        🗺️ Get directions
                    </a>
                </div>
            `).join("")}
            <div id="selectedReferralHospital" class="success" style="margin-top:12px;">No referral hospital selected yet.</div>
        `;
    } catch (error) {
        panel.innerHTML =
            `<div class="error">Could not load referral hospitals: ${escapeHtml(error.message)}</div>`;
    }
}

async function chooseReferralHospital(hospitalId, hospitalName, queueId)
{
    if (!confirm(`Choose ${hospitalName} as your referral hospital?`)) return;

    try {
        const result = await api("choose_referral_hospital", "POST", {
            queue_id: queueId,
            hospital_id: hospitalId
        });

        const panel = document.getElementById("selectedReferralHospital");
        if (panel) {
            panel.innerHTML = `🏥 <strong>Selected referral hospital:</strong> ${escapeHtml(result.hospital.name)}
                <br><span>You chose this hospital. You can use the directions link above to navigate there.</span>`;
        }
    } catch (error) {
        alert("Could not select hospital: " + error.message);
    }
}

async function loadLocation()
{
    const patientId = document.getElementById("patientId").value;
    try {
        const result = await api("patient_location&patient_id=" + encodeURIComponent(patientId));
        if (result.location) {
            document.getElementById("patientLocation").value = result.location;
        }
    } catch (error) {
        console.warn("Location could not be loaded:", error.message);
    }
}

async function updateLiveGPS(position)
{
    const now = Date.now();
    // Avoid unnecessary database writes while still keeping the location live.
    if (now - lastGpsSentAt < 10000) return;
    lastGpsSentAt = now;

    const patientId = document.getElementById("patientId").value;
    const latitude = position.coords.latitude;
    const longitude = position.coords.longitude;
    const accuracy = position.coords.accuracy;

    // Keep the raw coordinates private in the UI. Reverse-geocode them to a
    // human-readable place name for the patient.
    let placeName = "Current location";
    try {
        const geo = await fetch(
            `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${encodeURIComponent(latitude)}&lon=${encodeURIComponent(longitude)}&zoom=18&addressdetails=1`,
            { headers: { "Accept": "application/json", "Accept-Language": "en" } }
        );
        if (geo.ok) {
            const geoData = await geo.json();
            placeName = geoData.display_name || placeName;
        }
    } catch (e) {
        console.warn("Reverse geocoding unavailable:", e.message);
    }

    document.getElementById("patientLocation").value = `📍 ${placeName}`;

    document.getElementById("patientLocationStatus").innerHTML =
        `<div class="success">📍 LIVE GPS active · place: ${escapeHtml(placeName)} · accuracy ±${Math.round(accuracy)} m · updating automatically</div>`;

    try {
        await api("patient_location", "POST", {
            patient_id: patientId,
            latitude: latitude,
            longitude: longitude,
            location: placeName,
            accuracy_meters: accuracy
        });
    } catch (error) {
        document.getElementById("patientLocationStatus").innerHTML =
            `<div class="error">GPS obtained, but could not save it: ${escapeHtml(error.message)}</div>`;
    }
}

function startLiveGPS()
{
    if (!navigator.geolocation) {
        alert("This browser does not support live GPS location.");
        return;
    }

    if (gpsWatchId !== null) {
        document.getElementById("patientLocationStatus").innerHTML =
            `<div class="success">📍 LIVE GPS is already active.</div>`;
        return;
    }

    document.getElementById("patientLocationStatus").innerHTML =
        `<div class="empty">📍 Requesting GPS permission...</div>`;

    gpsWatchId = navigator.geolocation.watchPosition(
        updateLiveGPS,
        function(error) {
            let message = "Unable to get GPS location.";
            if (error.code === 1) message = "GPS permission was denied. Allow location access in the browser.";
            if (error.code === 2) message = "GPS location is currently unavailable.";
            if (error.code === 3) message = "GPS request timed out.";
            document.getElementById("patientLocationStatus").innerHTML =
                `<div class="error">📍 ${message}</div>`;
        },
        { enableHighAccuracy: true, maximumAge: 5000, timeout: 15000 }
    );
}

window.addEventListener("beforeunload", function () {
    if (gpsWatchId !== null) navigator.geolocation.clearWatch(gpsWatchId);
});

/*
|--------------------------------------------------------------------------
| Load queue
|--------------------------------------------------------------------------
*/

async function loadQueue()
{
    const hospitalId = document
        .getElementById("hospitalId")
        .value
        .trim();

    if (!hospitalId) {
        return;
    }

    try {
        const result = await api(
            "queue&hospital_id=" + encodeURIComponent(hospitalId)
        );

        displayQueue(result.queue || []);

        const patientId = document.getElementById("patientId").value;
        const referral = (result.queue || []).find(
            item => String(item.patient_id) === String(patientId)
                && item.status === "Referral"
        );
        if (referral) {
            loadReferralHospitals(referral.id);
        }
    }
    catch (error) {
        document.getElementById("queue").innerHTML =
            `<div class="error">${escapeHtml(error.message)}</div>`;
    }
}

function escapeHtml(value)
{
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

/*
|--------------------------------------------------------------------------
| Display queue
|--------------------------------------------------------------------------
*/

function displayQueue(queue)
{

    const container =
        document
        .getElementById(
            "queue"
        );

    const myPatientId = String(document.getElementById("patientId").value);
    const mine = queue.find(item => String(item.patient_id) === myPatientId);
    const positionEl = document.getElementById("patientQueuePosition");
    const waitEl = document.getElementById("patientEstimatedWait");
    if (positionEl) positionEl.textContent = mine ? "#" + (mine.position ?? "—") : "#—";
    if (waitEl) waitEl.textContent = mine ? ((mine.estimated_wait_minutes ?? "—") + " min") : "—";

    if (!queue.length) {

        container.innerHTML =
            `<div class="empty">
                No patients in queue.
            </div>`;

        return;
    }


    container.innerHTML =
        queue.map(
            patient => {

                const urgency =
                    patient.effective_urgency
                    .toLowerCase();


                const referralHospital = patient.referral_hospital_name || "Hospital not selected yet";

                return `

                <div class="queueItem ${patient.status === "Referral" ? "queueReferral" : ""}">

                    <div class="queueNumber">

                        #${escapeHtml(patient.queue_number)}

                    </div>


                    <div class="patientInfo">

                        <strong>
                            ${escapeHtml(patient.full_name)}
                        </strong>

                        <span>
                            👨‍⚕️ Doctor:
                            ${escapeHtml(patient.doctor_name || "Unassigned")}
                        </span>

                        <span>
                            Position:
                            ${escapeHtml(patient.position ?? "-")}
                        </span>

                        <span>
                            Waiting:
                            ${escapeHtml(patient.waiting_minutes)}
                            min
                        </span>

                        <span>
                            ${patient.status === "Referral"
                                ? `🏥 Referral: <strong>${escapeHtml(referralHospital)}</strong>`
                                : `Estimated wait: ${escapeHtml(patient.estimated_wait_minutes ?? "-")} min`}
                        </span>

                    </div>


                    <div>

                        <span class="
                            urgency
                            ${urgency}
                        ">

                            ${
                                patient.effective_urgency
                            }

                        </span>

                    </div>


                    <div>

                        <span>
                            Priority:
                        </span>

                        <strong>

                            ${
                                patient.priority_score
                            }

                        </strong>

                    </div>


                    <div>

                        <span class="
                            status
                        ">

                            ${escapeHtml(patient.status)}

                        </span>
                        ${patient.status === "Referral" ? `<span class="status-note">Hospital referral recorded. Choose a nearby Durban hospital below.</span>` : ""}

                    </div>

                </div>

                `;
            }
        )
        .join("");
}




/* Authenticated patient initialization */
window.addEventListener("DOMContentLoaded", function () {
    loadHospitals();
    loadLocation();
    startLiveGPS();
    loadQueue();
    setInterval(loadQueue, 2000);
});

</script>
</body>
</html>
