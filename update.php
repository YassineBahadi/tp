<?php
    require "connexion.php";
    if(isset($_GET['idUser'])){
        $id=$_GET['idUser'];
        $stmt=$pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $user=$stmt->fetch(PDO::FETCH_OBJ);
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

    <form  method="post">
        <input type="text" name="nom" value="<?=$user->nom?>" placeholder="Nom" required>
        <input type="text" name="prenom" value="<?=$user->prenom?>"  placeholder="Prenom" required>
        <input type="email" name="email" value="<?=$user->email?>" placeholder="Email" required>
        <button type="submit">Modifier</button>
    </form>

    <?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $email = $_POST['email'];

        $stmt = $pdo->prepare("UPDATE users SET nom=?, prenom=?, email=? WHERE id=?");
        $stmt->execute([$nom, $prenom, $email,$id]);

        header('Location: db.php');
    }
    ?>

</body>
</html>