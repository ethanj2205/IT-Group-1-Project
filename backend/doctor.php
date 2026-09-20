<?php
require_once __DIR__ . '/auth_common.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediQueue SA - Doctor Dashboard</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<header class="app-header">
  <div class="header-inner">
    <div class="brand">
      <div class="brand-mark">+</div>
      <div><h1>MediQueue SA</h1><p>Doctor Dashboard · Durban</p></div>
    </div>
    <div class="header-actions"><span class="live-pill">● LIVE</span><a class="logout" href="indexmedi.php">Exit</a></div>
  </div>
</header>

<main>
<section class="card">
  <div class="dashboard-title">
    <div><div class="eyebrow">Clinical workspace</div><h2>Doctor Dashboard</h2><p style="margin:4px 0 0">Live queue, consultations, prescriptions, referrals and doctor statistics.</p></div>
    <div class="avatar">DR</div>
  </div>

  <label>Hospital</label>
  <select id="doctorHospitalId" onchange="loadDoctorOptions()"><option value="">Loading hospitals...</option></select>

  <label>Doctor</label>
  <select id="doctorId" onchange="loadQueue()"><option value="">Choose your doctor</option></select>
  <small class="formHint">Doctors are assigned to their hospital. You can call any waiting patient in your selected hospital.</small>

  <div class="doctorActionGrid doctorActionGridEasy">
    <button id="takeNextBtn" class="primary take-next-btn" onclick="nextPatient()">🩺 Take Next Patient</button>
    <button class="secondary" onclick="loadStats()">📊 View Stats</button>
  </div>
  <div class="doctorHelp">The system automatically selects the next waiting patient using triage urgency and waiting time. You do not need to find the patient manually.</div>

  <div id="doctorStats" class="statsGrid"></div>
  <div id="doctorStatus"></div>
  <div id="consultationControls"></div>
</section>

<section class="card">
  <div class="dashboard-title">
    <div><div class="eyebrow">Next patient</div><h2>Virtual Queue</h2><p style="margin:4px 0 0">Prioritised by triage urgency.</p></div>
  </div>
  <div id="nextPatientCard" class="next-patient-card">Select a hospital and doctor to load the next patient.</div>
  <div id="queue">Loading queue...</div>
</section>

<section class="card full">
  <div class="queueHeader">
    <div><div class="eyebrow">Clinical tools</div><h2>Consultation workflow</h2><p>Call a patient, record notes, prescribe treatment or refer to hospital care.</p></div>
  </div>
  <div class="recordCard">
    <strong>Referral workflow</strong>
    <span>When a patient is referred, MediQueue uses their available location to rank nearby Durban hospitals and lets the patient choose where to go.</span>
  </div>
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
async function loadDoctorHospitals()
{
    const hospitalSelect = document.getElementById("doctorHospitalId");
    try {
        const result = await api("hospitals");
        hospitalSelect.innerHTML = `<option value="">Choose a hospital</option>` +
            (result.hospitals || []).map(h =>
                `<option value="${Number(h.id)}">${escapeHtml(h.name)} — ${escapeHtml(h.city)}</option>`
            ).join("");

        if (result.hospitals && result.hospitals.length) {
            hospitalSelect.value = String(result.hospitals[0].id);
            await loadDoctorOptions();
        }
    } catch (error) {
        hospitalSelect.innerHTML = `<option value="">Could not load hospitals</option>`;
    }
}

async function loadDoctorOptions()
{
    const hospitalId = document.getElementById("doctorHospitalId").value;
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
            `<option value="">Choose your doctor</option>` +
            doctors.map(d => `
                <option value="${Number(d.id)}" ${Number(d.available) ? "" : "disabled"}>
                    ${escapeHtml(d.full_name)}${Number(d.available) ? " — Available" : " — Unavailable"}
                </option>
            `).join("");

        const available = doctors.find(d => Number(d.available));
        if (available) {
            doctorSelect.value = String(available.id);
        }

        loadQueue();
        loadStats();
    } catch (error) {
        doctorSelect.innerHTML = `<option value="">Could not load doctors</option>`;
    }
}

async function callPatient(queueId)
{
    const doctorId = document.getElementById("doctorId").value;
    const hospitalId = document.getElementById("doctorHospitalId").value;

    if (!doctorId || !hospitalId) {
        alert("Choose your doctor and hospital first.");
        return;
    }

    if (!confirm("Call this patient into your consultation?")) return;

    try {
        const result = await api("queue_call_patient", "POST", {
            doctor_id: doctorId,
            hospital_id: hospitalId,
            queue_id: queueId
        });

        currentConsultationQueueId = result.queue_id;

        document.getElementById("doctorStatus").innerHTML = `
            <div class="success">
                Patient called successfully.<br>
                Queue: <strong>${escapeHtml(result.queue_number)}</strong><br>
                Patient ID: <strong>${escapeHtml(result.patient_id)}</strong><br>
                Doctor: <strong>${escapeHtml(result.doctor_name)}</strong><br>
                Status: <strong>In Consultation</strong>
            </div>`;

        document.getElementById("consultationControls").innerHTML = `
            <textarea id="consultationNotes" placeholder="Consultation notes..."></textarea>
            <br>
            <button onclick="completeConsultation()">Complete</button>
            <button class="secondary" onclick="addPrescription()">💊 Add Prescription</button>
            <button class="danger" onclick="referPatient()">🏥 Refer to Hospital</button>
        `;

        loadQueue();
    } catch (error) {
        alert(error.message);
    }
}

/*
|--------------------------------------------------------------------------
| Load queue
|--------------------------------------------------------------------------
*/

async function loadQueue()
{
    const hospitalId = document
        .getElementById("doctorHospitalId")
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

    const nextCard = document.getElementById("nextPatientCard");
    const next = queue.find(item => item.status === "Waiting");
    if (nextCard) {
        nextCard.innerHTML = next
            ? `<div class="next-patient-head"><span>NEXT PATIENT</span><b>${escapeHtml(next.queue_number)}</b></div>
               <strong>${escapeHtml(next.full_name)}</strong>
               <span>${escapeHtml(next.effective_urgency)} urgency · waiting ${escapeHtml(next.waiting_minutes)} min · estimated ${escapeHtml(next.estimated_wait_minutes ?? "-")} min</span>
               <button class="primary take-card-btn" onclick="nextPatient()">🩺 Take This Patient</button>`
            : `<strong>No waiting patients</strong><span>The queue is clear. New patients will appear here automatically.</span>`;
    }

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


                return `

                <div class="queueItem">

                    <div class="queueNumber">

                        #${escapeHtml(patient.queue_number)}

                    </div>


                    <div class="patientInfo">

                        <strong>
                            ${escapeHtml(patient.full_name)}
                        </strong>

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
                            Estimated wait:
                            ${escapeHtml(patient.estimated_wait_minutes ?? "-")}
                            min
                        </span>
                        <span>
                            📍 Patient location:
                            <strong>${escapeHtml(patient.location ?? "Location not available")}</strong>
                            ${patient.latitude !== null && patient.longitude !== null
                                ? ` · <a href="https://www.google.com/maps?q=${encodeURIComponent(patient.latitude + ',' + patient.longitude)}" target="_blank" rel="noopener">View on map</a>`
                                : ""}
                        </span>
                        ${patient.location_accuracy !== null && patient.location_accuracy !== undefined ? `<span>GPS accuracy: ±${Math.round(Number(patient.location_accuracy))} m</span>` : ""}

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

                        ${patient.status === "Waiting" ? `
                        <button class="secondary" style="margin-top:8px;"
                                onclick="callPatient(${Number(patient.id)})">
                            📣 Call This Patient
                        </button>` : ""}

                    </div>

                </div>

                `;
            }
        )
        .join("");
}


/*
|--------------------------------------------------------------------------
| Doctor takes next patient
|--------------------------------------------------------------------------
*/

async function nextPatient()
{
    const doctorId = document.getElementById("doctorId").value;
    const hospitalId = document.getElementById("doctorHospitalId").value;
    const takeButton = document.getElementById("takeNextBtn");

    if (!doctorId || !hospitalId) {
        alert("Choose your hospital and doctor first.");
        return;
    }

    if (currentConsultationQueueId) {
        alert("Complete the current consultation before taking another patient.");
        return;
    }

    if (takeButton) {
        takeButton.disabled = true;
        takeButton.textContent = "⏳ Taking Patient...";
    }

    try {
        const result = await api("queue_next", "POST", {
            doctor_id: doctorId,
            hospital_id: hospitalId
        });

        currentConsultationQueueId = result.queue_id;

        document.getElementById("doctorStatus").innerHTML = `
            <div class="active-consultation-card">
                <div class="active-consultation-top">
                    <div>
                        <span class="active-label">NOW SEEING</span>
                        <h3>${escapeHtml(result.patient_name || "Patient")}</h3>
                    </div>
                    <span class="consultation-status">IN CONSULTATION</span>
                </div>
                <div class="active-patient-meta">
                    <div><small>Queue Number</small><strong>#${escapeHtml(result.queue_number)}</strong></div>
                    <div><small>Patient ID</small><strong>${escapeHtml(result.patient_id)}</strong></div>
                    <div><small>Triage</small><strong>${escapeHtml(result.urgency || "Moderate")}</strong></div>
                </div>
            </div>`;

        document.getElementById("consultationControls").innerHTML = `
            <div class="consultation-card">
                <h3>Consultation</h3>
                <p class="muted">Record your notes, then complete the consultation or use one of the clinical actions.</p>
                <textarea id="consultationNotes" placeholder="Enter consultation notes, symptoms, diagnosis, treatment or important observations..."></textarea>
                <div class="consultationButtons">
                    <button class="primary" onclick="completeConsultation()">✅ Complete Consultation</button>
                    <button class="secondary" onclick="addPrescription()">💊 Prescription</button>
                    <button class="danger" onclick="referPatient()">🏥 Refer</button>
                </div>
            </div>`;

        if (takeButton) {
            takeButton.disabled = true;
            takeButton.textContent = "👤 Patient In Consultation";
        }

        loadQueue();
    } catch (error) {
        alert(error.message);
        if (takeButton) {
            takeButton.disabled = false;
            takeButton.textContent = "🩺 Take Next Patient";
        }
    }
}


/*
|--------------------------------------------------------------------------
| Doctor statistics
|--------------------------------------------------------------------------
*/
async function loadStats()
{
    const doctorId = document.getElementById("doctorId").value.trim();
    if (!doctorId) {
        alert("Enter Doctor ID.");
        return;
    }

    try {
        const result = await api(
            "doctor_stats&doctor_id=" + encodeURIComponent(doctorId)
        );

        document.getElementById("doctorStats").innerHTML = `
            <div class="statCard"><strong>${result.patients_today}</strong><span>Patients Today</span></div>
            <div class="statCard"><strong>${result.dealt_with}</strong><span>Dealt With</span></div>
            <div class="statCard"><strong>${result.waiting}</strong><span>Waiting</span></div>
            <div class="statCard"><strong>${result.in_consultation}</strong><span>In Consultation</span></div>
        `;
    } catch (error) {
        document.getElementById("doctorStats").innerHTML =
            `<div class="error">${escapeHtml(error.message)}</div>`;
    }
}

async function addPrescription()
{
    if (!currentConsultationQueueId) return;

    const medication = prompt("Medication name:");
    if (!medication || !medication.trim()) return;
    const instructions = prompt("Prescription description / instructions:") || "";
    const doseAmount = prompt("Dose amount (e.g. 2):") || "";
    const doseUnit = prompt("Dose unit (e.g. tablets, ml, capsules):") || "";
    const quantity = prompt("Quantity supplied (e.g. 30):") || "";
    const quantityUnit = prompt("Quantity type (tablets, bottles, capsules, etc.):") || "";
    const timesPerDay = prompt("How many times per day? (e.g. 2):") || "";
    const durationValue = prompt("How long should the patient take it? Number of days/weeks/months:") || "";
    const durationUnit = prompt("Duration unit (days/weeks/months):") || "";
    const medicationTimes = prompt("Exact times (24-hour, comma separated, e.g. 08:00,20:00):") || "";
    const startDate = prompt("Start date (YYYY-MM-DD):", new Date().toISOString().slice(0,10)) || "";
    const endDate = prompt("End date (YYYY-MM-DD, optional):") || "";
    const refillRequired = confirm("Will the patient return for a medication refill?");
    const refillDate = refillRequired ? (prompt("Refill date (YYYY-MM-DD):") || "") : "";
    const checkupRequired = confirm("Will the patient return for a check-up after finishing the medication?");
    const checkupDate = checkupRequired ? (prompt("Check-up date (YYYY-MM-DD):") || "") : "";
    const followupType = refillRequired && checkupRequired ? "Refill + Check-up" : (refillRequired ? "Refill" : (checkupRequired ? "Check-up" : ""));
    const followupNotes = (refillRequired || checkupRequired) ? (prompt("Follow-up notes (optional):") || "") : "";
    const doctorId = document.getElementById("doctorId").value;

    try {
        await api("prescription_create", "POST", {
            queue_id: currentConsultationQueueId,
            doctor_id: doctorId,
            medication: medication.trim(),
            instructions: instructions.trim(),
            dose_amount: doseAmount.trim(),
            dose_unit: doseUnit.trim(),
            quantity: quantity.trim(),
            quantity_unit: quantityUnit.trim(),
            times_per_day: timesPerDay.trim(),
            duration_value: durationValue.trim(),
            duration_unit: durationUnit.trim(),
            medication_times: medicationTimes.trim(),
            start_date: startDate.trim(),
            end_date: endDate.trim(),
            refill_required: refillRequired,
            refill_date: refillDate.trim(),
            checkup_required: checkupRequired,
            checkup_date: checkupDate.trim(),
            followup_type: followupType,
            followup_notes: followupNotes.trim()
        });

        alert("Detailed prescription saved for the patient.");
    } catch (error) {
        alert(error.message);
    }
}

/*
|--------------------------------------------------------------------------
| Complete consultation
|--------------------------------------------------------------------------
*/

async function completeConsultation()
{

    if (
        !currentConsultationQueueId
    ) {

        return;
    }


    const notes =
        document
        .getElementById(
            "consultationNotes"
        )
        .value;


    try {

        await api(
            "queue_complete",
            "POST",
            {
                queue_id:
                    currentConsultationQueueId,

                notes:
                    notes
            }
        );


        document
        .getElementById(
            "doctorStatus"
        )
        .innerHTML =

            `<div class="success">
                Consultation completed.
            </div>`;


        document
        .getElementById(
            "consultationControls"
        )
        .innerHTML = "";


        currentConsultationQueueId =
            null;

        const takeButton = document.getElementById("takeNextBtn");
        if (takeButton) {
            takeButton.disabled = false;
            takeButton.textContent = "🩺 Take Next Patient";
        }

        loadQueue();

    }

    catch (error) {

        alert(
            error.message
        );
    }
}


/*
|--------------------------------------------------------------------------
| Refer patient
|--------------------------------------------------------------------------
*/

async function referPatient()
{

    if (
        !currentConsultationQueueId
    ) {

        return;
    }


    const notes =
        document
        .getElementById(
            "consultationNotes"
        )
        .value;

    if (!notes.trim()) {
        alert("Enter the reason for the hospital referral in the consultation notes.");
        return;
    }

    if (!confirm("Refer this patient to hospital care because the case is urgent?")) {
        return;
    }

    try {

        await api(
            "queue_refer",
            "POST",
            {
                queue_id:
                    currentConsultationQueueId,

                notes:
                    notes
            }
        );


        let referralHtml = `<div class="success">Patient referred.</div>`;

        try {
            const referralResult = await api(
                "doctor_referral_hospitals&queue_id=" +
                encodeURIComponent(currentConsultationQueueId)
            );

            if (referralResult.gps_available && referralResult.hospitals.length) {
                referralHtml += `
                    <div class="recordCard">
                        <strong>🏥 Hospitals Near the Patient — Patient Chooses</strong>
                        ${referralResult.hospitals.map((h, i) => `
                            <span>
                                ${i + 1}. ${escapeHtml(h.name)}
                                — ${escapeHtml(h.distance_km)} km away
                                — <a href="${escapeHtml(h.directions_url)}" target="_blank" rel="noopener">Directions</a>
                            </span>
                        `).join("")}
                    </div>`;
            } else {
                referralHtml += `
                    <div class="empty">
                        Live GPS is not available, so nearby Durban hospitals could not be ranked.
                    </div>`;
            }
        } catch (hospitalError) {
            referralHtml += `
                <div class="empty">
                    Referral saved, but hospital suggestions could not be loaded.
                </div>`;
        }

        document.getElementById("doctorStatus").innerHTML = referralHtml;

        document
        .getElementById(
            "consultationControls"
        )
        .innerHTML = "";


        currentConsultationQueueId =
            null;

        const takeButton = document.getElementById("takeNextBtn");
        if (takeButton) {
            takeButton.disabled = false;
            takeButton.textContent = "🩺 Take Next Patient";
        }

        loadQueue();

    }

    catch (error) {

        alert(
            error.message
        );
    }
}




/* Automatic queue updates without SSE/reconnecting state. */
window.addEventListener("DOMContentLoaded", function () {
    loadDoctorHospitals();
    setInterval(loadQueue, 2000);
    setInterval(loadStats, 10000);
});


</script>
</body>
</html>
