<h1>TO-DO list</h1>
<div>
    <form method="POST">
        <input type="text" name="description" placeholder="Описание" value="" />
        <input type="submit" value="Добавить" />
    </form>
</div>
<?php
// функция redirect для очистки строки поиска от get запроса
require_once('redirect.php');

// Получаем логин и пароль для подключения
$columns = ['id', 'description', 'is_done', 'date_added'];
$names = ['Описание', 'Статус', 'Дата добавления'];
$conf = file_get_contents('config.json');
$conf = json_decode($conf, true);

// Подключение к БД
$connection = new PDO(
  "mysql:host=localhost;dbname=".$conf['user'].";charset=utf8",
   $conf['user'], $conf['pass']);

// Выборка данных
function SELECT($connection, $id=''){
  $select = "SELECT * FROM tasks";
  if (empty($id)){
    // Полная выборка
    $result = $connection->prepare($select);
    $result->execute();
  }
  else {
    $select.= " WHERE id = ?";
    $result = $connection->prepare($select);
    $result->execute([$id]);
  }
  return $result->fetchAll();
}

// Добавление данных
function INSERT($connection, $description)
{
  $insert = "INSERT INTO tasks (description, is_done, date_added)
  VALUES (?, ?, ?)";
  $connection->prepare($insert)
  ->execute([$description, "не выполнено", date('Y-m-d G:i:s',time())]);
}

// Обновление данных
function UPDATE($connection, $columns, $values){
  $task = SELECT($connection, $values['id']);
  $update = "UPDATE tasks SET description = :description,
    is_done = :is_done, date_added = :date_added WHERE id = :id";
  // Проверка полей
  $res = [];
  foreach ($columns as $key) {
    $res[$key] = ((!empty($values[$key])) ? $values[$key] : $task[0][$key]);
  }
  $connection->prepare($update)->execute($res);
}

// Удаление данных
function DELETE($connection, $id)
{
  $delete = "DELETE FROM tasks WHERE id = :id";
  $connection->prepare($delete)
  ->execute(['id'=>$id]);
}

// Создание таблицы
function CREATE_TABLE($connection)
{
  $create_table = "CREATE TABLE tasks (
    id int(11) NOT NULL AUTO_INCREMENT,
    description text NOT NULL,
    is_done text(11) NOT NULL,
    date_added datetime NOT NULL,
    PRIMARY KEY (id)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8";
  $connection->prepare($create_table)->execute();
}

function CHECK_TABLE($connection, $user)
{
  // Проверка на существование таблицы
  $check = "SHOW TABLES";

  $result = $connection->prepare($check);
  $result->execute();
  $res = $result->fetchAll();
  foreach ($res as $row) {
    if($row['Tables_in_'.$user] == 'tasks'){
      return true;
    }
  }
  return false;
}

// Еси таблица существует, то выполняем запросы
if (CHECK_TABLE($connection, $conf['user'])) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST'){
      if(isset($_POST['description'])){
        INSERT($connection, $_POST['description']);
      }
    }
    elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action'])) {
      switch ($_GET['action']) {
        case 'done':
          UPDATE($connection, $columns,
                 ['id'=>$_GET['id'], 'is_done'=>'выполнено']);
          redirect();
          break;
        case 'delete':
          DELETE($connection, $_GET['id']);
          redirect();
          break;
      }
    }

    $res = SELECT($connection);
    if (!empty($res)) {
      ?>
      <div>
      <table border="1">
        <tr>
          <?php foreach ($names as $value) {
            // Выводим шапку таблицы
            echo "<td><strong>$value</strong></td>";
          } ?>
          <td></td>
        </tr>
        <?php foreach ($res as $row) {
          // Выводим строки полученные запросом
          $id = $row['id'];
          ?>
          <tr>
            <?php foreach (array_slice($columns, 1) as $key) {
              echo "<td>$row[$key]</td>";
            }?>
            <td>
              <a href="?id=<?php echo $id?>&action=done">Выполнить</a>
              <a href="?id=<?php echo $id?>&action=delete">Удалить</a>
            </td>
          </tr>
        <?php }?>
        </table>
      </div>
      <?php
    }
    else{
      echo "<p>Дел нет</p>";
    }
  }
// иначе создаем таблицу
  else {
    CREATE_TABLE($connection);
    redirect();
  }
 ?>
