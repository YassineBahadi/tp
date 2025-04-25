<?php
    require "connexion.php";
    if(isset($_GET['idUser'])){
        $id=$_GET['idUser'];
        $stmt=$pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $user=$stmt->fetch(PDO::FETCH_OBJ);
        $nom=$user->nom;
        $prenom=$user->prenom;
        $email=$user->email;

    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add users</title>
</head>
<body>
    <form action="ajouter.php" method="post">
        <input type="hidden" name="idU" value="<?=isset($user)?$id:""?>" placeholder="Nom">
        <input type="text" name="nom" value="<?=isset($user)?$nom:""?>" placeholder="Nom" required>
        <input type="text" name="prenom"  value="<?=isset($user)?$prenom:""?>"  placeholder="Prenom" required>
        <input type="email" name="email" value="<?=isset($user)?$email:""?>" placeholder="Email" required>
        <?php 
            if(isset($id)){
                echo ' <input type="submit" name="modifier" value="Modifier">';
            }else{
                echo ' <input type="submit" name="ajouter" value="Ajouter">';
            }
        ?>
    </form>

    <?php
    if (isset($_POST['ajouter'])) {

        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $email = $_POST['email'];

        $stmt = $pdo->prepare("INSERT INTO users (nom, prenom, email) VALUES (?, ?, ?)");
        $stmt->execute([$nom, $prenom, $email]);

        header('Location: db.php');
    }
    if (isset($_POST['modifier'])) {
        $idU = $_POST['idU'];
        $nom = $_POST['nom'];
        $prenom = $_POST['prenom'];
        $email = $_POST['email'];

        $stmt = $pdo->prepare("UPDATE users SET nom=?, prenom=?, email=? WHERE id=?");
        $stmt->execute([$nom, $prenom, $email,$idU]);

        header('Location: db.php');
    }
    ?>
</body>
</html>