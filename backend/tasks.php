<?php
session_start();
require_once 'db_config.php';
if (empty($_SESSION['username'])) {
  header('Location: index.php');
  exit;
}
$id = $_SESSION['user_id'];
$conn = getMysqliConnection();
if ($conn->connect_error) die("Connection failed");
$weekdays = [
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
  'Sunday'
];
$showOld = true;
if (isset($_POST["noOld"])) {
  if ($_POST['noOld']) {
    $showOld = false;
  }
}

$todayDate = date("Y-m-d");

if ($showOld) {
  $result = $conn->query("SELECT * FROM tasks WHERE user = $id and start_date >= '$todayDate' ORDER BY start_date DESC");
} else {
  $result = $conn->query("SELECT * FROM tasks WHERE user = $id ORDER BY start_date DESC");
}
if (!$result) die("Query failed: " . $conn->error);
$userTasks = $result->fetch_all(MYSQLI_ASSOC);

$result = $conn->query("SELECT * FROM repeatable WHERE user = $id ORDER BY start_date DESC");
if (!$result) die("Query failed: " . $conn->error);
$userRepeatable = $result->fetch_all(MYSQLI_ASSOC);
$today = new DateTime();
$today->setTime(0, 0, 0);
$daysAhead = 21;

foreach ($userRepeatable as $rep) {
  $repStart = new DateTime($rep['start_date']);
  $repStart->setTime(0, 0, 0);
  if ($rep['active'] == 0) {
    continue;
  }

  $originalTime = (new DateTime($rep['start_date']))->format('H:i:s');

  for ($i = 0; $i < $daysAhead; $i++) {
    $checkDate = clone $today;
    $checkDate->modify("+$i days");
    $dateStr = $checkDate->format('Y-m-d') . ' ' . $originalTime;

    $shouldShow = false;

    if ($rep['daily']) {
      $shouldShow = true;
    } else {
      $currentWeekday = (int)$checkDate->format('N');
      if ($currentWeekday === (int)$rep['weekday']) {
        $shouldShow = true;
      }
    }

    $checkDateForCompare = clone $checkDate;
    $checkDateForCompare->setTime(0, 0, 0);

    if ($shouldShow && $checkDateForCompare >= $repStart) {
      $formattedDate = $checkDate->format('Y-m-d');

      $checkStmt = $conn->prepare("SELECT id FROM tasks WHERE repeatable_id = ? AND DATE(occurrence_date) = ? AND user = ?");
      $checkStmt->bind_param("isi", $rep['id'], $formattedDate, $id);
      $checkStmt->execute();
      $existing = $checkStmt->get_result()->fetch_assoc();
      if (!$existing) {
        $userTasks[] = [
          'id' => null,
          'repeatable_id' => $rep['id'],
          'name' => $rep['name'],
          'description' => $rep['description'],
          'start_date' => $dateStr,
          'end_date' => null,
          'status' => 'I',
          'type' => 'repeatable',
          'private' => $rep['private']
        ];
      }
    }
  }
}

usort($userTasks, function ($a, $b) {
  return strtotime($a['start_date']) - strtotime($b['start_date']);
});

if ($showOld) {
  $result = $conn->query("SELECT * FROM tasks WHERE user != $id AND private = 0 and start_date >= '$todayDate' ORDER BY start_date DESC");
} else {
  $result = $conn->query("SELECT * FROM tasks WHERE user != $id AND private = 0 ORDER BY start_date DESC");
}
if (!$result) die("Query failed: " . $conn->error);
$otherTasks = $result->fetch_all(MYSQLI_ASSOC);

$result = $conn->query("SELECT * FROM repeatable WHERE user != $id AND private = 0 ORDER BY start_date DESC");
if (!$result) die("Query failed: " . $conn->error);
$otherRepeatable = $result->fetch_all(MYSQLI_ASSOC);
$result = $conn->query("SELECT id,name FROM users");

if (!$result) die("Query failed: " . $conn->error);
$usersResult = $result->fetch_all(MYSQLI_ASSOC);

foreach ($usersResult as $user) {
  $users[$user['id']] = $user['name'];
}

foreach ($otherRepeatable as $rep) {
  $repStart = new DateTime($rep['start_date']);
  $repStart->setTime(0, 0, 0);
  if ($rep['active'] == 0) {
    continue;
  }

  $originalTime = (new DateTime($rep['start_date']))->format('H:i:s');

  for ($i = 0; $i < $daysAhead; $i++) {
    $checkDate = clone $today;
    $checkDate->modify("+$i days");
    $dateStr = $checkDate->format('Y-m-d') . ' ' . $originalTime;

    $shouldShow = false;

    if ($rep['daily']) {
      $shouldShow = true;
    } else {
      $currentWeekday = (int)$checkDate->format('N');
      if ($currentWeekday === (int)$rep['weekday']) {
        $shouldShow = true;
      }
    }

    $checkDateForCompare = clone $checkDate;
    $checkDateForCompare->setTime(0, 0, 0);

    if ($shouldShow && $checkDateForCompare >= $repStart) {
      $otherTasks[] = [
        'id' => null,
        'repeatable_id' => $rep['id'],
        'name' => $rep['name'],
        'description' => $rep['description'],
        'start_date' => $dateStr,
        'end_date' => null,
        'status' => 'I',
        'type' => 'repeatable',
        'user' => $rep['user']
      ];
    }
  }
}

usort($otherTasks, function ($a, $b) {
  return strtotime($a['start_date']) - strtotime($b['start_date']);
});
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Task Planner</title>
  <link rel="stylesheet" href="styles.css">

</head>

<body>
  <div>
    <form method="post"> <button type="submit" name="noOld" value="<?= $showOld ? "1" : "0" ?>"> <?= $showOld ? "" : "Don't" ?> Show Past Tasks</button> </form>
    <button id="showRecurrent">Show recurrent tasks</button>
    <button id="openPopup">Add new task</button>
    <button onclick="window.location.href='/index.php?logout=1'" class="logout-btn">Log out</button>

  </div>
  <h1>Your Tasks, <?= $_SESSION['username'] ?></h1>
  <?php if (empty($userTasks)): ?>
    <p>No tasks yet.</p>
  <?php else: ?>
    <table border="1" id="userNotrec">
      <thead>
        <tr>
          <th>Name</th>
          <th>Description</th>
          <th>Start Date</th>
          <th>End Date</th>
          <th>Private</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($userTasks as $task): ?>
          <tr>
            <td><?= htmlspecialchars($task['name']) ?></td>
            <td><?= htmlspecialchars($task['description']) ?></td>
            <td><?= htmlspecialchars($task['start_date']) ?></td>
            <td><?= htmlspecialchars((is_null($task['end_date'])) ? '-' : $task['end_date']) ?></td>
            <td><?= htmlspecialchars($task['private']) ? "private" : "-" ?></td>
            <td>
              <?php if ($task['id']): ?>
                <form class="statusForm" action="/api/server.php" method="post">
                  <input type="hidden" name="type" value="changeStatus">
                  <input type="hidden" name="taskId" value="<?= htmlspecialchars($task['id']) ?>">

                  <select name="taskStatus">
                    <option value="A" <?= $task['status'] === 'A' ? 'selected' : '' ?>>Active</option>
                    <option value="I" <?= $task['status'] === 'I' ? 'selected' : '' ?>>Inactive</option>
                    <option value="C" <?= $task['status'] === 'C' ? 'selected' : '' ?>>Canceled</option>
                    <option value="F" <?= $task['status'] === 'F' ? 'selected' : '' ?>>Finished</option>
                  </select>
                </form>
              <?php else: ?>
                <form class="statusForm" action="/api/server.php" method="post">
                  <input type="hidden" name="type" value="createAndChangeStatus">
                  <input type="hidden" name="repeatable_id" value="<?= htmlspecialchars($task['repeatable_id']) ?>">
                  <input type="hidden" name="taskName" value="<?= htmlspecialchars($task['name']) ?>">
                  <input type="hidden" name="taskDescription" value="<?= htmlspecialchars($task['description']) ?>">
                  <input type="hidden" name="taskDate" value="<?= htmlspecialchars($task['start_date']) ?>">
                  <input type="hidden" name="taskPrivate" value="<?= htmlspecialchars($task['private']) ?>">
                  <input type="hidden" name="taskUser" value="<?= htmlspecialchars($_SESSION['user_id']) ?>">
                  <select name="taskStatus">
                    <option value="A" <?= $task['status'] === 'A' ? 'selected' : '' ?>>Active</option>
                    <option value="C" <?= $task['status'] === 'C' ? 'selected' : '' ?>>Canceled</option>
                    <option value="I" <?= $task['status'] === 'I' ? 'selected' : '' ?>>Inactive</option>
                    <option value="F" <?= $task['status'] === 'F' ? 'selected' : '' ?>>Finished</option>
                  </select>
                </form>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($task['id']): ?>
                <button
                  type="button"
                  class="editBtn"
                  onclick='openEditForm(<?= json_encode($task, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                  Edit
                </button>
              <?php else: ?>
                <span>-</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <br>
      <?php if (empty($userRepeatable)): ?>
        <p>No tasks yet.</p>
      <?php else: ?>
        <table border="1" id="userRec" style="display:none;  ">
          <thead>
            <tr>
              <th>Name</th>
              <th>Description</th>
              <th>Start Date</th>
              <th>Weekday</th>
              <th>Active</th>
              <th>Private</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($userRepeatable as $repeatable): ?>
              <tr>
                <td><?= htmlspecialchars($repeatable['name']) ?></td>
                <td><?= htmlspecialchars($repeatable['description']) ?></td>
                <td><?= htmlspecialchars($repeatable['start_date']) ?></td>
                <td><?= isset($repeatable['weekday']) && $repeatable['weekday'] ? $weekdays[$repeatable['weekday']] : "Daily" ?></td>                <td><?= htmlspecialchars($repeatable['active']) ? "yes" : "no" ?></td>
                <td><?= htmlspecialchars($repeatable['private']) ? "private" : "-" ?></td>
                <td><button
                    type="button"
                    class="editBtn"
                    onclick='openEditForm(<?= json_encode($repeatable, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                    Edit
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    <?php endif; ?>

    <hr>
    <h1>Other Users' Public Tasks</h1>
    <?php if (empty($otherTasks)): ?>
      <p>No public tasks from other users.</p>
    <?php else: ?>
      <table border="1" id="otherNotrec">
        <thead>
          <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Status</th>
            <th>User</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($otherTasks as $task): ?>
            <tr>
              <td><?= htmlspecialchars($task['name']) ?></td>
              <td><?= htmlspecialchars($task['description']) ?></td>
              <td><?= htmlspecialchars($task['start_date']) ?></td>
              <td><?= htmlspecialchars((is_null($task['end_date'])) ? '-' : $task['end_date']) ?></td>
              <td>
                <?php
                $statusText = '';
                switch ($task['status']) {
                  case 'A':
                    $statusText = 'Active';
                    break;
                  case 'I':
                    $statusText = 'Inactive';
                    break;
                  case 'C':
                    $statusText = 'Canceled';
                    break;
                  case 'F':
                    $statusText = 'Finished';
                    break;
                  default:
                    $statusText = $task['status'];
                }
                echo htmlspecialchars($statusText);
                ?>
                <?= ($task['type'] ?? 'task') === 'repeatable' ? ' (Generated)' : '' ?>
              </td>
              <td><?= $users[$task['user']] ?></td>
              <td>
                <?php if ($task['id']): ?>
                  <button
                    type="button"
                    class="editBtn"
                    onclick='openEditForm(<?= json_encode($task, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                    Edit
                  </button>
                <?php else: ?>
                  <span>-</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    <?php if (empty($otherRepeatable)): ?>
      <p>No public recurrent tasks from other users.</p>
    <?php else: ?>
      <table border="1" id="otherRec" style="display:none;">
        <thead>
          <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Start Date</th>
            <th>Weekday</th>
            <th>Active</th>
            <th>User</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($otherRepeatable as $repeatable): ?>
            <tr>
              <td><?= htmlspecialchars($repeatable['name']) ?></td>
              <td><?= htmlspecialchars($repeatable['description']) ?></td>
              <td><?= htmlspecialchars($repeatable['start_date']) ?></td>
              <td><?= htmlspecialchars($repeatable['weekday'] ? $weekdays[$repeatable['weekday']] : 'Daily') ?></td>
              <td><?= htmlspecialchars($repeatable['active'] ? 'yes' : 'no') ?></td>
              <td><?= $users[$repeatable['user']] ?></td>
              <td><button
                  type="button"
                  class="editBtn"
                  onclick='openEditForm(<?= json_encode($repeatable, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                  Edit
                </button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>


    <div id="popupOverlay">
      <div id="popupModal">
        <div id="popupHeader">
          <h3>Add Task</h3>
          <button id="closePopup" type="button">X</button>
        </div>

        <form action="/api/server.php" method="post" id="popupForm">
          <input type="text" name="taskName" placeholder="Task name" required>
          <textarea name="taskDescription" placeholder="Short task description"></textarea>
          <input type="datetime-local" name="taskDate" required>

          <input type="hidden" name="type" value="addTask">

          <label class="checkboxRow">
            <input type="checkbox" name="taskRepeatable" id="taskRepeatable">
            Repeatable
          </label>

          <div id="repeatOptions" class="nested" aria-hidden="true">
            <label class="radioRow">
              <input type="radio" name="repeatType" value="daily">
              Daily
            </label>

            <label class="radioRow">
              <input type="radio" name="repeatType" value="weekly">
              Weekly
            </label>
          </div>

          <label class="checkboxRow">
            <input type="checkbox" name="taskPrivate" id="taskPrivate">
            Private
          </label>

          <button type="submit">Create a task</button>
        </form>
      </div>
    </div>

    <div id="editPopupOverlay">
      <div id="editPopupModal">
        <div id="editPopupHeader">
          <h3>Edit Task</h3>
          <button id="closeEditPopup" type="button">X</button>
        </div>

        <form action="/api/server.php" method="post" id="editPopupForm">
          <input type="hidden" name="type" value="editTask">
          <input type="hidden" name="taskId" id="editTaskId">
          <input type="hidden" name="taskUser" id="taskEditUse">

          <label>Task Name:</label>
          <input type="text" name="taskName" id="editTaskName" placeholder="Task name" required>

          <label>Description:</label>
          <textarea name="taskDescription" id="editTaskDescription" placeholder="Short task description"></textarea>

          <label>Start Date:</label>
          <input type="datetime-local" name="taskDate" id="editTaskDate">

          <label>Status:</label>
          <select name="taskStatus" id="editTaskStatus" required>
            <option value="A">Active</option>
            <option value="I">Inactive</option>
            <option value="C">Canceled</option>
            <option value="F">Finished</option>
          </select>

          <div id="endDateContainer">
            <label>End Date:</label>
            <input type="datetime-local"
              name="editTaskendDate"
              id="editTaskendDate">
          </div>

          <div id="recurrenceFields" style="display:none;">
            <br />
            <label class="checkboxRow">
              <input type="checkbox" name="taskDaily" id="taskDaily">
              Daily
            </label>
            <br>
            <label>Weekday:</label><br />
            <label class="radioRow">
              <input type="radio" name="taskWeekday" value="1"> Monday
            </label>
            <label class="radioRow">
              <input type="radio" name="taskWeekday" value="2"> Tuesday
            </label>
            <label class="radioRow">
              <input type="radio" name="taskWeekday" value="3"> Wednesday
            </label>
            <label class="radioRow">
              <input type="radio" name="taskWeekday" value="4"> Thursday
            </label>
            <label class="radioRow">
              <input type="radio" name="taskWeekday" value="5"> Friday
            </label>
            <label class="radioRow">
              <input type="radio" name="taskWeekday" value="6"> Saturday
            </label>
            <label class="radioRow">
              <input type="radio" name="taskWeekday" value="7"> Sunday
            </label>
          </div>

          <label class="checkboxRow">
            <input type="checkbox" name="taskPrivate" id="editTaskPrivate">
            Private
          </label>

          <button type="submit">Update Task</button>
        </form>
      </div>
    </div>
  <script src="script.js"></script>
</body>

</html>