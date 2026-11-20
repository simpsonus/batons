<?php
// Réponses figées des catalogues (TVmaze, TMDB, Google Books, RAWG) pour les tests hors ligne (BATONS_HTTP_FIXTURES).
return [
 '#api\.tvmaze\.com/search/shows\?q=bear#' => json_encode([
   ['score'=>0.9,'show'=>['id'=>61203,'name'=>'The Bear','premiered'=>'2022-06-23','image'=>['medium'=>'https://static.tvmaze.com/uploads/images/medium_portrait/1/1.jpg']]],
   ['score'=>0.5,'show'=>['id'=>111,'name'=>'Bear Grylls','premiered'=>'2010-01-01','image'=>null]],
 ]),
 '#api\.tvmaze\.com/shows/61203/episodes#' => json_encode(array_merge(
   array_map(fn($i)=>['season'=>1,'number'=>$i,'name'=>"E$i"], range(1,8)),
   array_map(fn($i)=>['season'=>2,'number'=>$i,'name'=>"E$i"], range(1,10)))),
 '#api\.tvmaze\.com/shows/61203$#' => json_encode(['id'=>61203,'name'=>'The Bear','premiered'=>'2022-06-23','image'=>null]),
 '#api\.themoviedb\.org/3/search/movie#' => json_encode(['results'=>[
   ['id'=>27205,'title'=>'Inception','original_title'=>'Inception','release_date'=>'2010-07-15','poster_path'=>'/x.jpg'],
   ['id'=>1,'title'=>'Le Fabuleux Destin','original_title'=>'Amélie','release_date'=>'2001-04-25','poster_path'=>null]]]),
 '#api\.themoviedb\.org/3/movie/27205#' => json_encode(['id'=>27205,'title'=>'Inception','original_title'=>'Inception','release_date'=>'2010-07-15','poster_path'=>'/x.jpg']),
 '#www\.googleapis\.com/books/v1/volumes\?#' => json_encode(['items'=>[
   ['id'=>'abc123XYZ','volumeInfo'=>['title'=>'Astérix le Gaulois','authors'=>['Goscinny','Uderzo'],'publishedDate'=>'1961']]]]),
 '#www\.googleapis\.com/books/v1/volumes/abc123XYZ#' => json_encode(['id'=>'abc123XYZ','volumeInfo'=>['title'=>'Astérix le Gaulois','authors'=>['Goscinny','Uderzo'],'publishedDate'=>'1961']]),
  '#api\.rawg\.io/api/games\?#' => json_encode(['results'=>[['id'=>3328,'name'=>'The Witcher 3: Wild Hunt','released'=>'2015-05-18','background_image'=>'https://media.rawg.io/media/games/x.jpg','platforms'=>[['platform'=>['name'=>'PC']],['platform'=>['name'=>'PlayStation 4']]]]]]),
  '#api\.rawg\.io/api/games/3328#' => json_encode(['id'=>3328,'name'=>'The Witcher 3: Wild Hunt','released'=>'2015-05-18','background_image'=>null]),
];
