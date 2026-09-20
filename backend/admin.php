<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MediQueue SA - Admin Dashboard</title>
<link rel="stylesheet" href="style.css">
<style>
.admin-main{display:block}
.kpi-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0}
.kpi{padding:18px;border:1px solid var(--line);border-radius:14px;background:#fff;box-shadow:var(--shadow)}
.kpi strong{display:block;font-size:30px;color:var(--navy);letter-spacing:-.04em}.kpi span{font-size:11px;color:var(--muted)}
.analytics-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.bar-row{display:grid;grid-template-columns:100px 1fr 40px;align-items:center;gap:9px;margin:13px 0;font-size:12px;font-weight:800}
.bar{height:9px;border-radius:99px;background:#e7ebef;overflow:hidden}.bar i{display:block;height:100%;border-radius:99px;background:var(--green)}
.bar-row:nth-child(2) .bar i{background:#d59b28}.bar-row:nth-child(3) .bar i{background:#c93232}
.alert-strip{margin-top:16px;padding:13px 15px;border-radius:12px;background:#fff4df;border:1px solid #f0d49a;color:#7e5b17;font-weight:800}
.admin-nav{display:flex;gap:8px;flex-wrap:wrap;margin-top:15px}.admin-nav a{padding:8px 11px;border-radius:9px;background:#f4f7f9;color:var(--navy);font-size:12px}
@media(max-width:800px){.kpi-grid{grid-template-columns:1fr 1fr}.analytics-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<header class="app-header">
<div class="header-inner">
<div class="brand"><div class="brand-mark">M</div><div><h1>MediQueue SA</h1><p>Admin Dashboard · Durban</p></div></div>
<div class="header-actions"><span class="live-pill">● LIVE</span><a class="logout" href="indexmedi.php">Exit</a></div>
</div>
</header>

<main class="admin-main">
<section class="card">
<div class="dashboard-title">
<div><div class="eyebrow">System analytics</div><h2>Admin Dashboard</h2><p id="period" style="margin:4px 0 0">Analytics — loading...</p></div>
<div class="avatar">A</div>
</div>
<div class="admin-nav"><a href="patient.php">Patient view</a><a href="doctor.php">Doctor view</a><a href="indexmedi.php">Home</a></div>
<div class="kpi-grid">
<div class="kpi"><strong id="consultations">—</strong><span>Consultations This Month</span></div>
<div class="kpi"><strong id="avoided">—</strong><span>Hospital Visits Avoided</span></div>
<div class="kpi"><strong id="avgWait">—</strong><span>Avg. Consult Time</span></div>
<div class="kpi"><strong id="referrals">—</strong><span>Hospital Referrals</span></div>
</div>
</section>

<section class="analytics-grid">
<section class="card">
<div class="section-label" style="margin-top:0">Triage Breakdown</div>
<div id="triageBars"><div class="empty">Loading analytics...</div></div>
</section>
<section class="card">
<div class="section-label" style="margin-top:0">Top Conditions This Month</div>
<div id="conditions"><div class="empty">Loading analytics...</div></div>
</section>
</section>

<section class="card">
<div class="alert-strip">⚠ Live analytics are connected to the MediQueue MySQL database and refresh every 10 seconds.</div>
</section>
</main>

<script>
async function loadAdminStats(){
  try{
    const response=await fetch('api.php?route=admin_stats',{headers:{Accept:'application/json'}});
    const data=await response.json();
    if(!response.ok || data.error) throw new Error(data.error||'Could not load analytics');
    document.getElementById('period').textContent='Analytics — '+data.month;
    document.getElementById('consultations').textContent=Number(data.consultations_this_month||0).toLocaleString();
    document.getElementById('avoided').textContent=(data.hospital_visits_avoided_pct||0)+'%';
    document.getElementById('avgWait').textContent=(data.avg_consult_wait_minutes||0)+' min';
    document.getElementById('referrals').textContent=Number(data.hospital_referrals||0).toLocaleString();

    const triage=data.triage||{Low:0,Moderate:0,High:0};
    const max=Math.max(1,...Object.values(triage).map(Number));
    const rows=[['Low Urgency','Low'],['Moderate','Moderate'],['High Urgency','High']];
    document.getElementById('triageBars').innerHTML=rows.map(([label,key])=>`
      <div class="bar-row"><span>${label}</span><div class="bar"><i style="width:${Math.round(Number(triage[key]||0)/max*100)}%"></i></div><b>${Number(triage[key]||0)}</b></div>
    `).join('');

    const conditions=data.top_conditions||{};
    const entries=Object.entries(conditions);
    document.getElementById('conditions').innerHTML=entries.length ? entries.map(([name,count],i)=>`
      <div class="recordCard"><strong>${i+1}. ${escapeHtml(name)}</strong><span>${Number(count).toLocaleString()} triage cases this month</span></div>
    `).join('') : '<div class="empty">No condition data yet.</div>';
  }catch(e){
    document.getElementById('conditions').innerHTML='<div class="error">'+escapeHtml(e.message)+'</div>';
  }
}
function escapeHtml(v){return String(v??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'","&#039;')}
window.addEventListener('DOMContentLoaded',()=>{loadAdminStats();setInterval(loadAdminStats,10000)});
</script>
</body>
</html>
