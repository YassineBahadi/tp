<?php
        require "connexion.php";

        if(isset($_GET['idUser'])){
            $id=$_GET['idUser'];
            $stmt=$pdo->prepare("DELETE FROM users WHERE id=?");
            $stmt->execute([$id]);
            header('location:db.php');
        }
?>