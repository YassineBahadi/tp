<?php
    require "connexion.php";
    $stmt=$pdo->query('SELECT * FROM users');

    $users=$stmt->fetchAll(PDO::FETCH_OBJ);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <table border="1" cellpadding="10" width="100%" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>nom Complet</th>
            <th>Email</th>
            <th>Supprimer</th>
            <th>modifier</th>
        </tr>
        <?php foreach($users as $user): ?>
            <tr>
                <td><?=$user->id?></td>
                <td><?=$user->nom?> <?=$user->prenom?></td>
                <td><?=$user->email?></td>
                <td> <a href="delete.php?idUser=<?=$user->id?>">Supprimer</a></td>
                <td> <a href="ajouter.php?idUser=<?=$user->id?>">Modifier</a> </td>
            </tr>
        <?php endforeach; ?>
    </table>
</body>
</html>