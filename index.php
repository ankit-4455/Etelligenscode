<?php
/* This is a Dummy Comment
used To show the Dummy User Data */

// User data to store user info

$userdata=[
	["name"=>"Abhishek",
	"age" =>36,
	"location"=>"India",
],
["name"=>"Swapnil",
"age" =>32,
"location"=>"Australia",
],
["name"=>"Manish",
"age" =>30,
"location"=>"London",
],
["name"=>"Monali",
"age" =>22,
"location"=>"India",
],
];
?>

<!DOCTYPE html>
<html>
<head>
	<!--Bootstrap css-->
	<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.3.7/dist/css/bootstrap.min.css" integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
	<!--Bootstrap css-->
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title></title>
</head>
<body>
	<div class="container">
		<div class="row">
			<h2 class="text-center">User Info</h2>	
			<div class="col-md-12">	
				<table class="table">
					<thead class="thead-dark">
						<tr>
							<th scope="col">S.No</th>
							<?php foreach($userdata[0] as $k=>$v):?>
								<th scope="col"><?= ucfirst($k)?></th>
							<?php endforeach;?>      
						</tr>
					</thead>	
					<tbody>
						<?php $i=1; foreach ($userdata as $key=>$val): ?>
						<tr>`
							<td><?= $i ?></td>
							<td><?= $val["name"] ?></td>	
							<td><?= $val["age"] ?></td>
							<td><?= $val["location"] ?></td>
						</tr>
						<?php $i++; endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>	
	</div>
</body>
</html>