<?php
session_start();
$host = 'localhost';
$user = '2417131';
$pass = 'University2025@#$&';
$dbname = 'db2417131';

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_error) die("Connection failed: " . $mysqli->connect_error);

$admin_hash = $mysqli->query("SELECT password FROM 5cs045_user LIMIT 1")->fetch_assoc()['password'] ?? '';

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    if (isset($_POST['login'])) {
        if (password_verify($_POST['password'], $admin_hash)) {
            $_SESSION['loggedin'] = true;
        } else {
            $login_error = "Incorrect password!";
        }
    }

    if (!isset($_SESSION['loggedin'])) {
        loginForm($login_error ?? '');
        die();
    }
}

$mysqli->query("CREATE DATABASE IF NOT EXISTS $dbname") or die($mysqli->error);
$mysqli->select_db($dbname);

$table_sql = "CREATE TABLE IF NOT EXISTS history_books (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(255) NOT NULL,
    genre VARCHAR(100),
    year INT,
    isbn VARCHAR(20),
    added_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$mysqli->query($table_sql);

if ($mysqli->query("SELECT COUNT(*) as c FROM history_books")->fetch_assoc()['c'] == 0) {
    $history_books = [
        ["The Guns of August", "Barbara Tuchman", "History", 1962, "9780345476098"],
        ["A Distant Mirror", "Barbara Tuchman", "History", 1978, "9780345349573"],
        ["Sapiens", "Yuval Noah Harari", "History", 2015, "9780062316097"],
        ["Guns, Germs, and Steel", "Jared Diamond", "History", 1997, "9780393317558"],
        ["The Silk Roads", "Peter Frankopan", "History", 2015, "9781408839973"],
        ["1491: New Revelations", "Charles C. Mann", "History", 2005, "9781400032051"],
        ["Postwar", "Tony Judt", "History", 2005, "9780143037750"],
        ["The Rise and Fall of the Third Reich", "William Shirer", "History", 1960, "9781451651683"],
        ["Team of Rivals", "Doris Kearns Goodwin", "History", 2005, "9780743270755"],
        ["Empire of the Summer Moon", "S.C. Gwynne", "History", 2010, "9781416591061"],
        ["The Plantagenets", "Dan Jones", "History", 2012, "9780007213927"],
        ["SPQR", "Mary Beard", "History", 2015, "9781631492221"]
    ];
    $stmt = $mysqli->prepare("INSERT INTO history_books (title, author, genre, year, isbn) VALUES (?, ?, ?, ?, ?)");
    foreach ($history_books as $b) {
        $stmt->bind_param("sssii", $b[0], $b[1], $b[2], $b[3], $b[4]);
        $stmt->execute();
    }
}

function render($template, $data = []) {
    extract($data);
    ob_start(); 
    include $template; 
    return ob_get_clean();
}

if (isset($_GET['autocomplete'])) {
    header('Content-Type: application/json');
    $term = '%' . $_GET['term'] . '%';
    $stmt = $mysqli->prepare("SELECT title FROM history_books WHERE title LIKE ? LIMIT 10");
    $stmt->bind_param("s", $term);
    $stmt->execute();
    $res = $stmt->get_result();
    $titles = [];
    while ($row = $res->fetch_assoc()) $titles[] = $row['title'];
    echo json_encode($titles);
    exit;
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title  = trim($_POST['title'] ?? '');
    $author = trim($_POST['author'] ?? '');
    $genre  = trim($_POST['genre'] ?? '');
    $year   = (int)($_POST['year'] ?? 0);
    $isbn   = trim($_POST['isbn'] ?? '');

    if ($_POST['action'] === 'add') {
        $stmt = $mysqli->prepare("INSERT INTO history_books (title, author, genre, year, isbn) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $title, $author, $genre, $year, $isbn);
        $stmt->execute();
        $msg = "Book added!";
    }

    if ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $mysqli->prepare("UPDATE history_books SET title=?, author=?, genre=?, year=?, isbn=? WHERE id=?");
        $stmt->bind_param("sssiii", $title, $author, $genre, $year, $isbn, $id);
        $stmt->execute();
        $msg = "Book updated!";
    }

    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $stmt = $mysqli->prepare("DELETE FROM history_books WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $msg = "Book deleted!";
    }
}

$where = [];
$params = [];
$types = '';

if (!empty($_GET['genre'])) { $where[] = "genre = ?"; $params[] = $_GET['genre']; $types .= 's'; }
if (!empty($_GET['year']))  { $where[] = "year = ?";  $params[] = $_GET['year'];  $types .= 'i'; }
if (!empty($_GET['search'])) {
    $where[] = "(title LIKE ? OR author LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
    $types .= 'ss';
}

$sql = "SELECT * FROM history_books";
if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY added_on DESC";

$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$history_books = $stmt->get_result();

// --- LOGOUT ---
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

echo render('php://memory', get_defined_vars());
?>

<?php function loginForm($error = ''): void { ?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
<div class="row justify-content-center">
<div class="col-md-4">
<div class="card shadow">
<div class="card-body">
<h3 class="card-title text-center mb-4">5CS045 Coursework Login</h3>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="post">
<div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Password" required></div>
<button type="submit" name="login" class="btn btn-primary w-100">Login</button>
</form>
</div></div></div></div></div>
</body></html>
<?php die(); }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>History history_books Manager - 5CS045 Coursework</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        body { background: #f8f9fa; }
        .table td { vertical-align: middle; }
        .autocomplete-suggestions {
            border: 1px solid #ddd; background: white; max-height: 200px; overflow-y: auto; position: absolute; z-index: 1000; width: 100%;
        }
        .autocomplete-suggestion { padding: 8px; cursor: pointer; }
        .autocomplete-suggestion:hover { background: #f0f0f0; }
    </style>
</head>
<body>
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="bi bi-book"></i> History history_books Manager</h1>
        <a href="?logout=1" class="btn btn-outline-danger">Logout</a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show">
        <?php echo htmlspecialchars($msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div><?php endif; ?>

    <div class="card mb-4 shadow-sm">
        <div class="card-body">
            <form class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search title/author..." value="<?php echo htmlspecialchars($_GET['search']??''); ?>" id="searchInput">
                    <div id="suggestions" class="autocomplete-suggestions" style="display:none;"></div>
                </div>
                <div class="col-md-3">
                    <select name="genre" class="form-select">
                        <option value="">All Genres</option>
                        <option value="History" <?php if(($_GET['genre']??'')==='History') echo 'selected'; ?>>History</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" class="form-control" name="year" placeholder="Year" value="<?php echo htmlspecialchars($_GET['year']??''); ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search"></i> Search</button>
                    <a href="index.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="mb-3 text-end">
        <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="bi bi-plus-circle"></i> Add New Book
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Genre</th>
                            <th>Year</th>
                            <th>ISBN</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i=1; while($book = $history_books->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['genre']); ?></td>
                            <td><?php echo htmlspecialchars($book['year']); ?></td>
                            <td><?php echo htmlspecialchars($book['isbn']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" onclick='editBook(<?php echo json_encode($book); ?>)' data-bs-toggle="modal" data-bs-target="#addModal">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this book?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo $book['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if($i===1): ?>
                        <tr><td colspan="7" class="text-center py-4">No history_books found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" id="bookForm">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="modalTitle">Add New Book</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="add" id="formAction">
          <input type="hidden" name="id" id="bookId">
          <div class="mb-3"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Author</label><input type="text" name="author" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Genre</label><input type="text" name="genre" class="form-control" value="History" required></div>
          <div class="mb-3"><label class="form-label">Year</label><input type="number" name="year" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">ISBN</label><input type="text" name="isbn" class="form-control"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Book</button>
        </div>
      </div>
    </form>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editBook(book) {
    document.getElementById('modalTitle').textContent = 'Edit Book';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('bookId').value = book.id;
    document.querySelector('[name="title"]').value = book.title;
    document.querySelector('[name="author"]').value = book.author;
    document.querySelector('[name="genre"]').value = book.genre;
    document.querySelector('[name="year"]').value = book.year;
    document.querySelector('[name="isbn"]').value = book.isbn;
}

document.getElementById('searchInput').addEventListener('input', function(e) {
    let val = this.value;
    let sugg = document.getElementById('suggestions');
    if (val.length < 2) { sugg.style.display='none'; return; }
    
    fetch(`index.php?autocomplete=1&term=${encodeURIComponent(val)}`)
        .then(r => r.json())
        .then(data => {
            sugg.innerHTML = '';
            if (data.length === 0) { sugg.style.display='none'; return; }
            data.forEach(title => {
                let div = document.createElement('div');
                div.className = 'autocomplete-suggestion';
                div.textContent = title;
                div.onclick = () => { e.target.value = title; sugg.style.display='none'; };
                sugg.appendChild(div);
            });
            sugg.style.display = 'block';
        });
});
document.addEventListener('click', e => {
    if (!e.target.closest('#searchInput') && !e.target.closest('#suggestions'))
        document.getElementById('suggestions').style.display='none';
});
</script>
</body>
</html>
