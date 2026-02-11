document.addEventListener("DOMContentLoaded", () => {
    let currentUser = null;
/* ---------- HOME PAGE IMAGE SLIDER ---------- */

const slides = [
    "jku 1.jpg",
    "graduation.jpg",
    "election.jpg"
];

let slideIndex = 0;
const slideImg = document.getElementById("slide-img");

function changeSlide() {
    slideIndex = (slideIndex + 1) % slides.length;
    slideImg.style.opacity = 0;

    setTimeout(() => {
        slideImg.src = slides[slideIndex];
        slideImg.style.opacity = 1;
    }, 500);
}

// Change image every 3 seconds
setInterval(changeSlide, 3000);

    // --- NAVIGATION LOGIC ---
    const showPage = (id) => {
        document.querySelectorAll(".page").forEach(p => p.classList.add("hidden"));
        const target = document.getElementById("page-" + id);
        if (target) target.classList.remove("hidden");
    };

    document.querySelectorAll("[data-link]").forEach(link => {
        link.onclick = (e) => { e.preventDefault(); showPage(link.dataset.link); };
    });

    /* --- REGISTRATION --- */
    document.getElementById("btn-register").onclick = async () => {
        const fd = new FormData();
        fd.append("action", "register");
        fd.append("name", document.getElementById("reg-name").value);
        fd.append("studentID", document.getElementById("reg-studentid").value);
        fd.append("fayda", document.getElementById("reg-fayda").value);
        fd.append("fingerprint", document.getElementById("reg-fingerprint").value);

        const res = await fetch("index.php", { method: "POST", body: fd }).then(r => r.json());
        const msg = document.getElementById("register-msg");
        msg.innerHTML = res.status === "success" 
            ? "<span style='color:green'>Success! You can now login.</span>" 
            : `<span style='color:red'>${res.msg}</span>`;
    };
    

    /* --- LOGIN UI SWITCH --- */
    document.getElementById("login-role").onchange = (e) => {
        const isAdmin = e.target.value === "admin";
        document.getElementById("admin-login-fields").classList.toggle("hidden", !isAdmin);
        document.getElementById("student-login-fields").classList.toggle("hidden", isAdmin);
    };

    /* --- LOGIN (ROLE-BASED) --- */
    document.getElementById("btn-login").onclick = async () => {
        const roleReq = document.getElementById("login-role").value;
        const fd = new FormData();

        if (roleReq === "admin") {
            fd.append("action", "admin_login");
            fd.append("username", document.getElementById("login-username").value);
            fd.append("password", document.getElementById("login-password").value);
        } else {
            const fp = prompt("Scan Fingerprint (Simulated: Type your fingerprint)");
            if (!fp) return;
            fd.append("action", "student_login");
            fd.append("fayda", document.getElementById("login-fayda").value);
            fd.append("fingerprint", fp);
        }

        const res = await fetch("index.php", { method: "POST", body: fd }).then(r => r.json());
        
        if (res.status === "success") {
            if (res.role === "admin") {
                showPage("admin-dashboard");
                loadAdminData();
            } else {
                currentUser = res; 
                document.getElementById("sd-welcome").innerText = "Welcome, " + res.name;
                showPage("student-dashboard");
                loadCandidates();
            }
        } else {
            alert(res.msg);
        }
    };

    /* --- ADMIN DASHBOARD ACTIONS --- */

    // 1. Add Candidate
    document.getElementById("btn-add-cand").onclick = async () => {
        const fd = new FormData();
        fd.append("action", "add_candidate");
        fd.append("name", document.getElementById("cand-name").value);
        fd.append("dept", document.getElementById("cand-dept").value);
        fd.append("year", document.getElementById("cand-year").value);
        fd.append("exp", document.getElementById("cand-exp").value);

        const res = await fetch("index.php", { method: "POST", body: fd }).then(r => r.json());
        if (res.status === "success") {
            alert("Candidate Added!");
            loadAdminData();
            document.getElementById("form-add-candidate").reset();
        }
    };

    // 2. Refresh List
    document.getElementById("btn-refresh-cands").onclick = () => loadAdminData();

    // 3. View Voters Button
    document.getElementById("btn-view-voters").onclick = async () => {
        const fd = new FormData();
        fd.append("action", "view_voters");
        const data = await fetch("index.php", { method: "POST", body: fd }).then(r => r.json());
        let list = "Registered Voters:\n";
        data.forEach(v => {
            list += `${v.name} (${v.student_id}) - ${v.has_voted == 1 ? "VOTED" : "NOT VOTED"}\n`;
        });
        alert(list);
    };

    // 4. Publish Results Button
    document.getElementById("btn-publish").onclick = () => {
        if(confirm("Close Election and Publish Results?")) {
            loadResults();
            alert("Results Published!");
        }
    };

    // 5. Unpublish/Reopen
    document.getElementById("btn-unpublish").onclick = () => {
        document.getElementById("admin-results").innerHTML = "Election Reopened. Results Hidden.";
        alert("Election Reopened!");
    };

    /* --- DATA LOADING FUNCTIONS --- */

    async function loadAdminData() {
        const fd = new FormData();
        fd.append("action", "get_candidates");
        const data = await fetch("index.php", { method: "POST", body: fd }).then(r => r.json());
        
        const adminCandList = document.getElementById("admin-candidates");
        adminCandList.innerHTML = "";
        data.forEach(c => {
            adminCandList.innerHTML += `
                <div class='card' style='display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;'>
                    <span><strong>${c.name}</strong> - ${c.department}</span>
                    <div>
                        <button class='btn small' onclick='editCandidate(${c.id}, "${c.name}")'>Edit</button>
                        <button class='btn secondary small' onclick='deleteCandidate(${c.id})'>Delete</button>
                    </div>
                </div>`;
        });
        
        // Update Registered Voter Count display
        const fdV = new FormData();
        fdV.append("action", "view_voters");
        const voters = await fetch("index.php", { method: "POST", body: fdV }).then(r => r.json());
        document.getElementById("voter-count").innerText = voters.length;

        document.getElementById("ad-welcome").innerText = "Welcome, Administrator";
    }

    // This shows the vote counts when you click Publish
    async function loadResults() {
        const fd = new FormData();
        fd.append("action", "get_results");
        const data = await fetch("index.php", { method: "POST", body: fd }).then(r => r.json());
        const resArea = document.getElementById("admin-results");
        resArea.innerHTML = "<h4>Final Election Results</h4>";
        data.forEach(r => {
            resArea.innerHTML += `<p>${r.name}: <strong>${r.total} votes</strong></p>`;
        });
    }

    /* --- GLOBAL HELPERS (Bound to Window) --- */

    window.deleteCandidate = async (id) => {
        if(!confirm("Are you sure you want to delete this candidate?")) return;
        const fd = new FormData();
        fd.append("action", "delete_candidate");
        fd.append("id", id);
        await fetch("index.php", { method: "POST", body: fd });
        loadAdminData();
    };

    window.editCandidate = async (id, oldName) => {
        const newName = prompt("Enter new name for candidate:", oldName);
        if(!newName) return;
        const fd = new FormData();
        fd.append("action", "edit_candidate");
        fd.append("id", id);
        fd.append("name", newName);
        await fetch("index.php", { method: "POST", body: fd });
        loadAdminData();
    };

    /* --- STUDENT VOTING LOGIC --- */

    document.getElementById("go-to-vote").onclick = () => {
        document.getElementById("page-vote").classList.remove("hidden");
        document.getElementById("go-to-vote").classList.add("hidden");
    };

    document.getElementById("btn-cancel-vote").onclick = () => {
        document.getElementById("page-vote").classList.add("hidden");
        document.getElementById("go-to-vote").classList.remove("hidden");
    };

    async function loadCandidates() {
        const fd = new FormData();
        fd.append("action", "get_candidates");
        const data = await fetch("index.php", { method: "POST", body: fd }).then(r => r.json());
        
        const list = document.getElementById("candidates-list");
        const opts = document.getElementById("vote-options");
        list.innerHTML = ""; opts.innerHTML = "";

        data.forEach(c => {
            list.innerHTML += `<div class='card'><strong>${c.name}</strong> (${c.department})</div>`;
            opts.innerHTML += `<label><input type='radio' name='c' value='${c.id}'> ${c.name}</label><br>`;
        });
    }

    document.getElementById("btn-vote").onclick = async () => {
        const sel = document.querySelector("input[name='c']:checked");
        if (!sel) return alert("Select a candidate");

        const fd = new FormData();
        fd.append("action", "vote");
        fd.append("candidateID", sel.value);
        fd.append("studentID", currentUser.studentID);

        const res = await fetch("index.php", { method: "POST", body: fd }).then(r => r.json());
        if (res.status === "success") {
            alert("Vote Cast Successfully!");
            location.reload(); 
        } else {
            alert(res.msg);
        }
    };

    // Logout
    document.querySelectorAll("#student-logout, #admin-logout").forEach(b => {
        b.onclick = () => location.reload();
    });
});