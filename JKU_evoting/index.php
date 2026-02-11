<?php
$conn = new mysqli("localhost", "root", "", "jku_evot12");
if ($conn->connect_error) die("DB Error");

/* HANDLE AJAX REQUESTS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {



  /* REGISTER STUDENT */
  if ($_POST["action"] === "register") {
    $stmt = $conn->prepare(
      "INSERT INTO users (name, student_id, fayda_id, fingerprint)
       VALUES (?,?,?,?)"
    );
    $stmt->bind_param("ssss",
      $_POST["name"],
      $_POST["studentID"],
      $_POST["fayda"],
      $_POST["fingerprint"]
    );

    if ($stmt->execute()) {
      echo json_encode(["status"=>"success"]);
    } else {
      echo json_encode(["status"=>"error","msg"=>"Student already exists"]);
    }
    exit;

    /* --- NEW ADMIN ACTIONS --- */

  // 1. DELETE CANDIDATE
  if ($_POST["action"] === "delete_candidate") {
      $id = (int)$_POST["id"]; 
      $conn->query("DELETE FROM votes WHERE candidate_id = $id"); // Delete votes first
      $conn->query("DELETE FROM candidates WHERE id = $id");
      echo json_encode(["status" => "success"]);
      exit;
  }

  // 2. VIEW VOTERS LIST
  if ($_POST["action"] === "view_voters") {
      $res = $conn->query("SELECT name, student_id, has_voted FROM users");
      $voters = [];
      while($row = $res->fetch_assoc()) $voters[] = $row;
      echo json_encode($voters);
      exit;
  }

  // 3. GET RESULTS (Total Votes)
  if ($_POST["action"] === "get_results") {
      $sql = "SELECT c.name, COUNT(v.id) as total 
              FROM candidates c 
              LEFT JOIN votes v ON c.id = v.candidate_id 
              GROUP BY c.id";
      $res = $conn->query($sql);
      $results = [];
      while($row = $res->fetch_assoc()) $results[] = $row;
      echo json_encode($results);
      exit;
  }
  }

  /* STUDENT LOGIN */
  if ($_POST["action"] === "student_login") {
    $stmt = $conn->prepare(
      "SELECT * FROM users WHERE fayda_id=?"
    );
    $stmt->bind_param("s", $_POST["fayda"]);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
      $u = $res->fetch_assoc();
      if ($u["fingerprint"] === $_POST["fingerprint"]) {
        echo json_encode([
          "status"=>"success",
          "name"=>$u["name"],
          "studentID"=>$u["student_id"]
        ]);
      } else {
        echo json_encode(["status"=>"error","msg"=>"Fingerprint mismatch"]);
      }
    } else {
      echo json_encode(["status"=>"error","msg"=>"Student not found"]);
    }
    exit;
  }

 /* ADMIN LOGIN */
  if ($_POST["action"] === "admin_login") {
    $stmt = $conn->prepare(
      "SELECT * FROM admin WHERE username=? AND password=?"
    );
    $stmt->bind_param("ss", $_POST["username"], $_POST["password"]);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows === 1) {
      // Added 'role' => 'admin' so the JS knows to show the Admin Dashboard
      echo json_encode(["status" => "success", "role" => "admin"]);
    } else {
      echo json_encode(["status" => "error", "msg" => "Invalid credentials"]);
    }
    exit;
  }

  /* ADD CANDIDATE */
  if ($_POST["action"] === "add_candidate") {
    $stmt = $conn->prepare(
      "INSERT INTO candidates (name, department, ac_year, experience)
       VALUES (?,?,?,?)"
    );
    $stmt->bind_param("ssss",
      $_POST["name"],
      $_POST["dept"],
      $_POST["year"],
      $_POST["exp"]
    );
    $stmt->execute();
    echo json_encode(["status"=>"success"]);
    exit;
  }

  /* CAST VOTE (SECURE VERSION) */
  if ($_POST["action"] === "vote") {
    $studentID = $_POST["studentID"];
    $candidateID = $_POST["candidateID"];

    // 1. Check if the student has already voted
    $check = $conn->prepare("SELECT has_voted FROM users WHERE student_id = ?");
    $check->bind_param("s", $studentID);
    $check->execute();
    $userData = $check->get_result()->fetch_assoc();

    if ($userData && $userData['has_voted'] == 1) {
        echo json_encode(["status" => "error", "msg" => "You have already cast your vote!"]);
    } else {
        // 2. Insert the vote using a prepared statement
        $stmt = $conn->prepare("INSERT INTO votes (student_id, candidate_id) VALUES (?, ?)");
        $stmt->bind_param("si", $studentID, $candidateID);
        
        if ($stmt->execute()) {
            // 3. Mark the student as having voted
            $update = $conn->prepare("UPDATE users SET has_voted = 1 WHERE student_id = ?");
            $update->bind_param("s", $studentID);
            $update->execute();
            echo json_encode(["status" => "success"]);
        } else {
            echo json_encode(["status" => "error", "msg" => "Failed to record vote."]);
        }
    }
    exit;
  }
/* GET CANDIDATES (for both student & admin) */
if ($_POST["action"] === "get_candidates") {
  $res = $conn->query("SELECT * FROM candidates ORDER BY name ASC");
  $candidates = [];
  while ($row = $res->fetch_assoc()) $candidates[] = $row;
  echo json_encode($candidates);
  exit;
}

}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>JKU Student E-Voting (Demo)</title>
<link rel="stylesheet" href="tesfa.css"> </link>

</head>
<body>

<header>
  <h1>JKU — Student E-Voting (Demo)</h1>
  <nav class="navlinks">
    <a href="#" data-link="home">Home</a>
    <a href="#" data-link="about">About</a>
    <a href="#" data-link="election">Election</a>
    <a href="#" data-link="register">Registration</a>
    <a href="#" data-link="login">Login</a>
  </nav>
</header>

<main>

  <!-- HOME -->
  <section id="page-home" class="page card">
    <div class="grid cols-2">
      <div>
        <div class="carousel" id="carousel">
          <div id="home-slider">
    <img id="slide-img" src="jku 1.jpg">
</div>
<div id="home-slider">
    <img id="slide-img" src="graduation.jpg">
</div>
<div id="home-slider">
    <img id="slide-img" src="election.jpg">
</div>

          <div class="caption"><strong id="carousel-title">Jinka University</strong></div>
        </div>
        <div style="margin-top:12px" class="row">
          <button class="btn" id="to-about">Learn about JKU</button>
          <button class="btn secondary" id="to-election">About Election</button>
          <span class="right muted">Demo site — uses browser storage</span>
        </div>
      </div>
      <div>
        <h2  style="color: blue;
font-style: italic;
text-align: center;
font-size: 15px;
padding-top: 25px;">Welcome to the JKU E-Voting demo</h2>
        <center><i><h2 class="muted">Knowledge For change</h2></i></center>

        <div class="card" style="margin-top:14px">
          <h3 class="large"> </h3>
          <div style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap">
            <button class="btn" data-link="register">Register</button>
            <button class="btn secondary" data-link="login">Login</button>
            <button class="btn" id="clear-data">Reset Demo Data</button>
          </div>
        </div>
      </div>
    </div>

   
  </section>

  <!-- ABOUT -->
  <section id="page-about" class="page card hidden">
    <h2>About Jinka University (short)</h2>
    <p class="muted">Jinka University (JKU) is a public higher education institution established to expand access to quality education in the South Omo Zone. It provides undergraduate programs across multiple colleges and supports community engagement and research. This site demonstrates an e-voting platform to modernize student president elections, improve transparency and reduce manual errors.</p>

    <h3>University Snapshot</h3>
    <ul class="muted">
      <li>Location: Jinka, South Omo Zone</li>
      <li>Founded: 2015</li>
      <li>Focus: Teaching, Research, Community Service</li>
    </ul>
  </section>

  <!-- ELECTION -->
  <section id="page-election" class="page card hidden">
    <h2>About Election</h2>
    <p class="muted">This election is for Student President at Jinka University. The e-voting system ensures authentication (via Fayda ID simulation), one vote per student, and secure vote counting. The system logs activities for auditing.</p>

    <h3>Benefits of e-voting</h3>
    <ul class="muted">
      <li>Faster, automated vote counting</li>
      <li>Improved transparency and audit trails</li>
      <li>Reduced human errors and queues</li>
      <li>Secure verification using national ID (simulated here)</li>
    </ul>

    <h3>Rules for the election</h3>
    <ol class="muted">
      <li>Only eligible JKU students may vote (verified via Fayda ID).</li>
      <li>Each student may cast exactly one vote.</li>
      <li>Voting is confidential; individual choices are not displayed publicly.</li>
      <li>Results are published only after the admin closes the election.</li>
      <li>All administrative actions are logged for audit.</li>
    </ol>
  </section>

  <!-- REGISTER -->
  <section id="page-register" class="page card hidden">
    <h2>Student Registration</h2>
    <p class="muted">Fill the form to register for the election. Fayda ID and a fingerprint value (simulated) are required to verify you later.</p>
    <form id="form-register" onsubmit="return false;">
      <label>Full name
        <input id="reg-name" required />
      </label>
      <label>Student ID
        <input id="reg-studentid" required />
      </label>
      <label>Fayda ID
        <input id="reg-fayda" required />
      </label>
      <label>Fingerprint (simulated - type a string to represent fingerprint)
        <input id="reg-fingerprint" required />
        <div class="small">This is a simulated fingerprint stored locally; in a real system this would be a biometric template sent to Fayda API.</div>
      </label>

      <div style="display:flex;gap:8px;margin-top:12px">
        <button class="btn" id="btn-register">Register</button>
        <button class="btn secondary" id="link-login">Go to Login</button>
      </div>
      <div id="register-msg" style="margin-top:10px"></div>
    </form>
  </section>

  <!-- LOGIN -->
  <section id="page-login" class="page card hidden">
    <h2>User Login</h2>
    <p class="muted">Select your role. Admins enter username/password. Students must provide Fayda ID and a fingerprint to verify.</p>
    <form id="form-login" onsubmit="return false;">
      <label>Role
        <select id="login-role">
          <option value="student">Student</option>
          <option value="admin">Admin</option>
        </select>
      </label>

      <div id="student-login-fields">
        <label>Fayda ID
          <input id="login-fayda" />
        </label>
      </div>

      <div id="admin-login-fields" class="hidden">
        <label>Username
          <input id="login-username" />
        </label>
        <label>Password
          <input id="login-password" type="password" />
        </label>
      </div>

      <div style="display:flex;gap:8px;margin-top:12px">
        <button class="btn" id="btn-login">Login</button>
        <button class="btn secondary" id="link-register-2">Register instead</button>
      </div>

      <div id="login-msg" style="margin-top:10px"></div>
    </form>
  </section>

  <!-- STUDENT DASHBOARD -->
  <section id="page-student-dashboard" class="page card hidden">
    <div class="row">
      <h2>Student Dashboard</h2>
      <div class="right">
        <span id="sd-welcome" class="muted"></span>
        <button class="btn secondary" id="student-logout">Logout</button>
      </div>
    </div>

    <div class="card">
      <h3>Candidate Details</h3>
      <div id="candidates-list" style="display:grid;gap:10px"></div>
      <div style="margin-top:10px" class="center">
        <button class="btn" id="go-to-vote">Go to Vote</button>
      </div>
    </div>

    <div id="page-vote" class="card hidden">
      <h3>Cast Your Vote</h3>
      <form id="vote-form" onsubmit="return false;">
        <div id="vote-options"></div>
        <div style="margin-top:12px">
          <button class="btn" id="btn-vote">Submit Vote</button>
          <button class="btn secondary" id="btn-cancel-vote">Cancel</button>
        </div>
        <div id="vote-msg" style="margin-top:10px"></div>
      </form>
    </div>

    <div class="card">
      <h3>View Result</h3>
      <div id="result-area" class="muted">If the election is finished and results published by admin, results will appear here.</div>
    </div>
  </section>

  <!-- ADMIN DASHBOARD -->
  <section id="page-admin-dashboard" class="page card hidden">
    <div class="row">
      <h2>Admin Dashboard</h2>
      <div class="right">
        <span id="ad-welcome" class="muted"></span>
        <button class="btn secondary" id="admin-logout">Logout</button>
      </div>
    </div>

    <div class="grid cols-2">
      <div class="card">
        <h3>Manage Candidates</h3>
        <form id="form-add-candidate" onsubmit="return false;">
          <label>Candidate Name
            <input id="cand-name" required />
          </label>
          <label>Department
            <input id="cand-dept" required />
          </label>
          <label>AC Year
            <input id="cand-year" required />
          </label>
          <label>Experience / Short Bio
            <textarea id="cand-exp" rows="2"></textarea>
          </label>
          <div style="display:flex;gap:8px;margin-top:8px">
            <button class="btn" id="btn-add-cand">Add Candidate</button>
            <button class="btn secondary" id="btn-refresh-cands">Refresh</button>
          </div>
        </form>

        <h4 style="margin-top:12px">Existing Candidates</h4>
        <div id="admin-candidates"></div>
      </div>

      <div class="card">
        <h3>Voters & Results</h3>
        <div class="small">Registered voters: <strong id="voter-count">0</strong></div>
        <div style="margin-top:10px">
          <button class="btn" id="btn-view-voters">View voters (no votes shown)</button>
          <button class="btn secondary" id="btn-view-logs">View logs</button>
        </div>

        <h4 style="margin-top:12px">Election Controls</h4>
        <div style="display:flex;gap:8px">
          <button class="btn" id="btn-publish">Publish Results (Close Election)</button>
          <button class="btn secondary" id="btn-unpublish">Unpublish / Reopen Election</button>
        </div>

        <div style="margin-top:12px">
          <h4>Vote counts</h4>
          <div id="admin-results" class="muted">No results yet</div>
        </div>
      </div>
    </div>
  </section>

</main>

<footer>
  <div>© 2025 JKU E-Voting G2 CS </div> 
</footer>

<script src="tesf.js"></script>


</body>
</html>

