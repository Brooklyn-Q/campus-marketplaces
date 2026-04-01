/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_tiers` (
  `tier_name` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `duration` varchar(50) NOT NULL DEFAULT 'forever',
  `product_limit` int(11) NOT NULL DEFAULT 2,
  `images_per_product` int(11) NOT NULL DEFAULT 1,
  `badge` varchar(50) NOT NULL DEFAULT 'blue',
  `ads_boost` tinyint(1) NOT NULL DEFAULT 0,
  `priority` varchar(50) NOT NULL DEFAULT 'normal',
  PRIMARY KEY (`tier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `account_tiers` VALUES ('basic',0.00,'forever',4,1,'#0000ff',0,'normal'),('premium',0.00,'weekly',18,3,'#ffd700',0,'top'),('pro',0.00,'2_weeks',7,2,'#c0c0c0',0,'normal');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ad_placements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `image_url` varchar(500) DEFAULT '',
  `link_url` varchar(500) DEFAULT '#',
  `placement` enum('homepage','category','product') DEFAULT 'homepage',
  `is_active` tinyint(1) DEFAULT 1,
  `impressions` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ai_recommendations_cache` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `context_hash` varchar(64) NOT NULL,
  `recommended_product_ids` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `context_hash` (`context_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `ai_recommendations_cache` VALUES (1,'9ee2d5cd58f0ccedee0c1c2095abebe3a750e532fe0c83f8b90e4f8d3bf8d482','16,18,17','2026-03-28 08:43:15'),(2,'0229c7987f30bd60111d125ff87e970bd3c28bf5c9aa8231de505d28e0bc8d7c','15,18,16','2026-03-28 08:50:10'),(3,'0bff6be5107efdd58f021664b23851342839b563a3a5e9558fd15e52413bf16f','15,18,17','2026-03-28 08:50:47'),(4,'51299a1febfbd9d2cd9f163ff79e45df32f80bee3ee5eba183e26d76aabdc5bf','15,18,16,17','2026-03-28 08:52:22'),(5,'4b6421129b3600657405e843b2a0521f280b5637d84a32d5aeded8a1a6e034a8','16,18,17','2026-03-28 08:52:49'),(6,'5b7fb6dbcba171622b824dbcd38a9f7c74d0a610441382b1194442fd1fe67d62','15,18,17','2026-03-28 08:52:55'),(7,'ffd837d6085f3bf1f766f5780c34d49f59ceaaececae547b7d1de998b014ca5b','15,16,18','2026-03-28 08:53:01'),(9,'180ae9d257228c012b538876c0071283b22c73810ac04fa0bbb670771cabfca4','15,18,16','2026-03-28 08:53:29'),(11,'7a8321d9561d7f9ffb85fd6e9a097842bffe50be74c2583c003355760841197a','15,16,18,17','2026-03-28 09:36:05'),(14,'480cba158fc6b72216e916d6febb1ff68b985c12eb773f902393f5804acf2e5d','15,16,17','2026-03-28 09:45:53'),(15,'99a2bf7884b23bf5c432ca17674bacba9ff64e5afd3529a5bebf58bd86acb6ac','18,16,17','2026-03-28 09:46:44'),(16,'6c67aab503920483038ee706bd23a96b3368e0c3b0ca047d27242aa67cba01b3','18,16,17','2026-03-28 09:47:29'),(17,'6b89597b1bf5f0bd75a71b8d9100e655e5d15b1271d837eefd770aac5afb0dd5','18,16,17','2026-03-28 09:48:50'),(18,'6966b01aa400c8e1f14943fef6a1667cc83a4723de4dda94160d1b751a79755a','15,18,16,17','2026-03-28 09:51:14'),(19,'a80a7ef74f45c5ac7a005b2c6a1ce503e89f7eb7b6ab3d6aa2f2ccbdda7b88ef','15,18,17','2026-03-28 09:53:49'),(20,'27f4d498d0feb318ba4968c6f82ac55d7df9a116a18b30b92cd528adcf2fb28a','15,18,16','2026-03-28 10:00:22'),(22,'bfc8f92fb0a402319c8d7bb67f5cf1c5499e429a7b9dce657cf384667794a4e7','16,18,17','2026-03-28 10:25:46'),(23,'30a1805bd6ca83f8676e817e660ba4b1c7245d15eb4a9e17531d93b26032962f','18,16,17','2026-03-28 10:25:55'),(24,'8c7cbb08a3e23745a6d0e81df3b31a397347f9703bc92355ced6ddd35f62d725','15,16,18,17','2026-03-28 10:26:08'),(29,'ea388d96566f8a51cdbca87475658857d564bffc68e506f9f0573c3e17455787','15,18,17','2026-03-28 11:15:57'),(30,'98c43cc7281e26176ed562921456709359b3c633fd320c69a120b75bb5cce44e','15,16,18,17','2026-03-28 11:25:37'),(31,'e863e7c64bb0eb6fd45c7f6e917d491991c378419004e916161f3a2215ed6978','16,18,17','2026-03-28 11:26:01'),(32,'d7b6c08f083da231755a7ecd8fb00b3546033d3b8959c4921f65a0ebb5ae528f','15,16,18,17','2026-03-28 11:27:23'),(33,'9827da148301e9b9ab40bfa97846858af5acfbc1633f2be6e5945c6246d64226','15,16,18','2026-03-28 11:27:41'),(35,'8185cd3859a5ff630856950d35572dac3f7d8484c6518499739119204d047fb1','15,18,17','2026-03-28 11:28:20'),(36,'d73f22290d520042d8afad443baa0ade2af4421ba7743484c8cce4dbb0219d80','15,16,18,17','2026-03-28 11:35:25'),(38,'664608f6c206b1bf2c28e1b2a95092a58f8dbee981d57e0ae5ac7f9004879e0b','21,24,22,23','2026-03-28 12:50:09'),(39,'e34a50b4b23392bb5d773c92ab6d4254989b0d07546f8857fd7aba66800ed86f','19,24,20,21','2026-03-28 12:50:22'),(40,'dc9fc37ebc2e8ee9604c5d004c052c4a32f2540685fea04635a52d60e3c37888','22,19,20,23','2026-03-28 12:52:24'),(42,'0298f1d60c4e87007c5392c3c97394b504b89af2ed8d6b3c134d9b029a5b6906','24,19,22,20,25,23','2026-03-28 13:10:38'),(43,'3ed22a32e8c9ff6b01c7e99d8d1a72d2b5250d76c1349986e25f6a0eef8e44aa','22,19,23,25','2026-03-28 13:14:59'),(44,'797eb43ddb4ed5bc247e37fed7f6248ec876b5155d075cc373a2fbeac6c367a1','24,19,22,21','2026-03-28 13:15:31'),(45,'6ee04a550f03af9954a1ff1808ddad4ff2a29c704172644cfd2c06c0431b22f9','24,22,19,23','2026-03-28 13:15:39'),(46,'61cc2dffb020cdc36d5a7c44459b757103b8d2d48df001ac195e1425b3b80947','26,24,19,22,21,20','2026-03-28 13:15:48'),(47,'35c6e6e8f57511c28f43632d7abd7fb499d1ef85564518fbf2221bfdcfa11b1d','24,22,19,25','2026-03-28 13:16:25'),(49,'31f3f8ecf22818a8482218c7ab0275e786963deee98da803d5931e15a43957a3','26,24,19,25','2026-03-28 14:38:17'),(50,'07c7b214e61f770fcbdbde14f5718f42c26309dad33d3d2277a0618d652a210b','26,24,22,19,23,21','2026-03-28 14:39:40'),(51,'3eb8d5cf5b0630df24a1221cd059538883b7247a8b7a0e7332a42dcc6370a651','22,24,19,25','2026-03-28 14:40:45'),(53,'4fe7dba8e332a3a118da8ce5f3d7d5d63d37b758248daea00d24f3744af5c27d','26,24,22,19','2026-03-28 14:48:06'),(54,'1c6b657c11232e79db8555e8f9724d8c2d4a94c2bece324eca221955e60657c1','26,22,24,19,23,25','2026-03-28 14:48:18'),(57,'0662b6c45f557c2c9d0e913a3987b35f1523587885b7165f912076e98fb45792','26,24,23,19','2026-03-28 15:09:12'),(58,'03543cbb8a6c97da637d557752e3a48c178453a8ef8092a38b1ece294f53df32','22,24,19,23','2026-03-28 15:12:28'),(61,'1c3abb7b2454dfd0a43a7795ecc1537e28d2d7df62faabe44ad364282b971b7a','22,24,19,23','2026-03-28 15:28:01'),(62,'8f6700229a254c97fb39ca4f0942d162888d434618ed8372a1b2f522221924ce','26,24,22,19,23,20','2026-03-28 16:11:24'),(64,'142ae3ba6c8ebdb9a987722e69611027d46163762be55bc5e247c58632dcc41c','24,22,23,19','2026-03-28 16:13:04'),(65,'4f295dd093ec240eeb860ed0642500d93ff891c258e91de0c64f2efe7954386f','26,22,24,19,23,20','2026-03-28 16:13:14'),(66,'f53e90ac1b656d5843d4c3b9ef93705b305ffd66e54bc216af311604dbdf82f7','26,22,24,19','2026-03-28 16:13:19'),(67,'e23410549417781c335c77c1a99e49c5e2529e73c6188551dfa7b6a39ced05bb','26,24,22,21,23,19','2026-03-28 16:13:46'),(68,'87c1e9f1a1ab8cb432f64f0bdc887a39cdb914537f8a3751c4b0dfa1bae97db9','26,24,23,21','2026-03-28 16:13:52'),(73,'c52cdc04c5684bb6c757beac38dcf0b2b7fbb16f16708c9d4beb9702572d0204','22,24,19,21','2026-03-28 17:15:32'),(74,'9691db37dfee8dac27ec3cfc9158b4c706306eb2266697ddc8a9e355ba312bef','26,24,21,19','2026-03-28 17:18:57'),(75,'b2741f05b947bd9a9ea66ae44d560bb9e302faa2e6601350c6248848db12203f','26,24,21,23','2026-03-28 17:19:11'),(76,'f37f6896c078c4c6cf87865549e92c8770040d3166df9d1a666170556342d7cf','26,24,21,23','2026-03-28 17:19:33'),(79,'3112c767ceba4273960dd710f6b598b1d7a44b0da5c97fcb3e422a412ef0e35a','26,22,24,21','2026-03-28 18:04:36'),(80,'e756cbeabb4bd54d1878e9324ca62095a04ce3e008042ff8a639c69c43229409','26,22,24,20','2026-03-28 18:06:54'),(81,'7c35b5d686d948e4a10049569920c743b7d342ecba1b8059feffc0de7445fb74','26,22,24,21','2026-03-28 18:07:11'),(82,'8b90df75ff99e05acd5d7a7e1bdfed6b16a04e26fb0e04229421ea31656bf3ad','26,22,24,23','2026-03-28 18:07:12'),(83,'f44776c509353aace664ef1a8b56eafde01f6f260c1c88c72389667a8f72545d','26,22,24,21','2026-03-28 18:07:13'),(84,'a99c1a4050984964cca2bd140444ccdedad6e28440564b330b85be0d105e98b6','26,22,24,20','2026-03-28 18:07:15'),(85,'76085f266f195273803fec297e53dba0705f52961c9bb900e7d104ba07958d54','26,22,24,23','2026-03-28 18:07:16'),(86,'4aa6973cb5678cd0413218406bb30d9758447c281a5033d904e68212e30cbeae','26,22,24,20','2026-03-28 18:07:17'),(87,'8957a597b5080edda4f24a8e26f2d2955bd74a4fafa1113252508c93702be7e3','26,22,24,20','2026-03-28 18:07:18'),(88,'fe5c3336e6f12bd81f421337f1fbd78e984d14995617d70b1eb72ab6ee73b325','26,22,24,20','2026-03-28 18:07:19'),(89,'4c3ab81caa094d48d1f3b6718f1511a791627a3e0c9194c47111fafa773d7c4e','26,22,24,20','2026-03-28 18:07:20'),(90,'7d0ee4125c3bd229863b64296552e6bd7594257677db9deeae63b5324ac9b1d0','19,26,22,24','2026-03-28 18:07:26'),(91,'b8d2c5981dda7f2fc1f69ee6868badde91a4b1c932df64cda2212143516c5705','19,26,22,24,21,23','2026-03-28 18:12:11'),(93,'49ec61481b669e3440d260a78b093e262447da18c4db3a32fca047ac2d03fbd5','26,22,24,21','2026-03-28 18:20:58'),(95,'30d43b7e14d92f0a1c936dcdd9c58cc5c765249676df5fe0d2184ed4d23f9ebc','19,26,22,21,24,23','2026-03-28 18:44:59'),(96,'50dde27a233150236d987536366555d1f92b34764df69979f81d3cc44a759b1c','19,26,22,24,21,20','2026-03-28 18:56:28'),(101,'492f508add3cf9187ce0a1ec52dfab1423e84e8c27a8774947d99b0e0dc1a95e','22,21','2026-03-28 20:31:51'),(102,'30064740792d1ac1451f41878e5ee2371c294e97d1f0e472abc2ec62f05ba3e9','22,21','2026-03-28 20:32:05'),(104,'f94a83cbfae6f9b4f4a3208fabb237da2e039868081583893142da213e2f39a2','19,22,21,24','2026-03-28 20:56:36'),(106,'36e406ca4489b32b370e6c49be37a3a7d707e7f3e7d42a0d7940dc16eef5b686','26,22,24,21','2026-03-28 20:57:50'),(107,'0bbf5cfe99d0e9eb3870860ccc5a689f9eafffe0c94b7104aabdd976f1a71440','26,22,21,24','2026-03-28 20:58:13'),(110,'8f964111cbcf5d59dacd1dde39ad8d8a567c16486fb0ff8977a088da373192d1','22,21','2026-03-28 21:27:12'),(114,'0c2be69f11e3d8f17f32ef415787279c64e5cf89e39b558cadfb1b0088c15113','19,26,22,24,21,23','2026-03-28 22:48:25'),(121,'5d4578f096083ef11fdc8fb9b32739405bea64bcb82e7ca24ff2aed5f383ab27','19,22,21,24','2026-03-29 00:26:37'),(122,'2aebf44f5b18467b9b4d6f53392314448ddd6ef4d2b6009224bb87fe5ea9e857','19,22,24,21','2026-03-29 00:27:11'),(123,'0f2a1d15e958d9fc6033bffef587f004d8c7cf5e1c6a838353684c1e9708508d','19,26,22,24,21,20','2026-03-29 00:29:51'),(124,'0585965dfc0bc911d6efc3e697839a3219d6bdc2442786bb89bacdfdfebfc81a','19,20','2026-03-29 00:30:49'),(125,'32aeec6a32df2a7641ffb287d67aa093b61462859e091605fb88f12727961afa','19,20','2026-03-29 00:31:36'),(126,'399588eed1c1604a01b28fdec5de6c7a7a7bec054307ff00670ba7a0dab9e87e','19,26,22,24,21,20','2026-03-29 00:34:38'),(127,'b27202fa350a4b2dcba14f532870451554c9317f4501e672bd75d4b728c37282','19,26,22,21','2026-03-29 00:38:08'),(132,'a25cb3d00b0ff4411bb88cde48e1815b89266654b79862b5d20a4cd1f98bdc9e','22,21','2026-03-29 01:06:22'),(133,'ab6addab78df74f92ec50da90f81414b2852e55f6a8bc11f11998a71bf953ee3','22,27','2026-03-29 01:06:28'),(134,'084168baee380a0854a0bd107439eec488a5bad09efdd35985172210d8529e75','19,28','2026-03-29 01:06:31'),(135,'fe9cda6607004ef49b6d420e798005893963cc8df01b8a1d1587546acdfccc56','19,26,22,21','2026-03-29 01:06:36'),(136,'5360f7913ec7787a0666eb92d4e5296a63b7c76c3fb6057630e8880100006531','19,26,22,21,24,23','2026-03-29 01:06:44'),(137,'172527ed0898bfebd47e2113d5f32c265b2dbee61e20b839795f66a4a6238bd6','19,22,21,23','2026-03-29 01:06:53'),(138,'b199d77b528bae68b9273b3850f079e21a386b02cd94cbd768f3f60451f15b59','19,26,22,21,24,23','2026-03-29 01:10:47'),(139,'e28624f171a78b4fa7ce8c001f2cd9f80d886c07f5bd15a7b28d4cb58c68046c','22,21','2026-03-29 01:11:03'),(142,'f76dc07f9709a50ea771cdadc773090667e54870f83dacfe639290030edb8067','19,26,22,21','2026-03-29 10:51:41'),(143,'838c445806ee0aafaf9e46416b5f53175d4ff267b3ba1f13ee917d929f11b2c5','19,26,22,21','2026-03-29 10:51:49'),(144,'7e6d87b32c6e1c20a4a228f65d9011c00380cc5f04bf0514aa2f1ae86c198568','19,26,22,21','2026-03-29 10:51:50'),(145,'dd5fea15f535a2251497eee3740b18e2592ff83a30c5d9b01c95397d062b45fd','19,26,22,24,21,23','2026-03-29 10:55:02'),(146,'b6855ff2a991606f72bc7c2fc7a6a16f32e7dcce3015c6ad29becdbbe8d3ea92','19,22,24,21','2026-03-29 11:02:40'),(147,'0901259fc672a1cb236632c6bf78d7eea0e654b3cd932a0f27a2d492a292b311','19,20','2026-03-29 11:04:43'),(148,'4a690c12d1cfeda93c718c7e39cfb38e4cce0ae058b3ef6c59bed7885f971a87','20,28','2026-03-29 11:04:51'),(149,'628c6e270f525bded28cfc8337b7356abca46e03c63c3747c100460fd890f638','19,26,22,24,21,20','2026-03-29 11:07:44'),(150,'c1e585730de387101b95db2928a2ba16cc564055a629bfa91478946573fe57f8','19,22,24,21','2026-03-29 11:08:33'),(151,'71f9cc4965a969b6bf76128fb0e2350b435c62da939f0c4cf5ce7ea34247eb0c','19,26,22,24,21,25','2026-03-29 11:10:55'),(152,'fa1d0a102aa7c3261f7e6d002869094218de9c437e7eab19d11b27f032a925c7','27,21','2026-03-29 11:11:41'),(153,'ca40901de03f45a31401e7ceb3581cf09433469a467cf1fa106cf1fa66fb9f81','19,26,22,24,21,23','2026-03-29 11:12:40'),(154,'e9b5e4ba3a89d5303ef914bc6cc36703100cdcc4628205dff202fcb931dc2e9c','19,26,22,24','2026-03-29 11:12:46'),(155,'619e759ad2d41b0c0ffe817612736e3121fc21fe94c73405fbaf3ea4d014d221','19,26,22,24','2026-03-29 11:12:54'),(156,'06509c1ff09fac116b5d4b803c75af19fc06a947618e365a42af706cfe11bb3b','19,26,22,24,23,21','2026-03-29 11:14:00'),(157,'1b70c3f70b30137a3e1de065ac1623315597cd49124e1ef9cd2edd6537d90876','19,26,22,23','2026-03-29 11:14:51'),(158,'830b575a4db4f0300e0959b301c6c697513acea23db19b5ac3a42d7153d2709a','19,26,22,24,23,21','2026-03-29 11:15:26'),(159,'e0ce1d89b99eb47c7e83634441f774e064c9aa9261eeac590f086d91adb74da3','19,26,22,24','2026-03-29 11:16:06'),(160,'d2211691d3a9b1d01b769930c6926a8320e615c7e6b51671e55b3ffa547a739d','19,20','2026-03-29 11:16:23'),(170,'612d8f432c888e3d28c7c63f88dd07c781452b761379dd854ee40784ed2c2e6c','19,20','2026-03-29 12:43:57'),(171,'e31a9e775547f16e27e0052b03494b2cbbb8d9213c99937e18f59c00e7347d56','19,26,22,27,24,23','2026-03-29 12:44:49'),(172,'593ade33ff9d0a02881dc972f1034d27ad6d865e5ed1841ef9cab6558f460b60','19,22,27,24','2026-03-29 12:46:35'),(173,'1ffed3cf07fec8e75bed76e066cb31f6c4bec9cedb316f9ad1942e73049e4cdf','26,19,22,27,24,23','2026-03-29 12:47:30'),(174,'e38e0788f4c5d37fa98e2d26386bc6383751420222464715c5416fd4dd61a948','19,22,24,27','2026-03-29 12:48:11'),(175,'ffb0d4b538755db763a98c0319d8828b5120c8907b49db7cef56be4a3cbeb3af','19,20','2026-03-29 12:48:24'),(176,'870b560c2ca8dbc19e471d79e3f37896168542773fc8b1c73609c99ec9e9d977','19,20','2026-03-29 12:50:09'),(177,'7cd4dd78619dec3e7186d17e1cda4fb65c02ebde66c7cf6678a2635270cd42ac','19,22,27,24','2026-03-29 12:52:51'),(178,'0126698c856e528c2489f143051649b5da6caeaadeb49a55a990e828fd14007d','26,19,22,27,24,23','2026-03-29 12:53:00'),(179,'c721fa95cf09f556f1b0f58cb7d4341135eec480eb609d531a2d1fb8ddeac07b','22,21','2026-03-29 12:54:34'),(183,'b72b78dada1e4804b373d4bfa3c8f97fe29d9423e57c7ebb9fceaa773f4371c9','26,19,22,24,27,23','2026-03-29 13:30:37'),(184,'d4e4b03249ad018b6b0d558d0f3502353d6b66c1e3699295dd68d6702964e307','26,19,22,24','2026-03-29 13:31:10'),(185,'5958f3c6a06d30026229e980069eca4c23fddfe37c9ff1ef1607d14a7c439297','26,19,22,27,23,24','2026-03-29 13:31:18'),(188,'9044654550732b5b84b93778313180586043431d0e44ab54cdcfd1fac16fc164','26,19,22,24,27,23','2026-03-29 14:11:25');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` enum('info','warning','success','danger') DEFAULT 'info',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `announcements` VALUES (1,1,'how are you all','info',0,'2026-03-29 12:17:25','2026-03-29 12:19:57'),(2,1,'let me know if you need anything else','danger',0,'2026-03-29 12:19:02','2026-03-29 12:19:55');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `audit_log` VALUES (1,1,'Cleared all audit logs','',0,'2026-03-29 14:55:34');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_orders` (
  `id` int(11) NOT NULL DEFAULT 0,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('ordered','seller_seen','delivered','completed','disputed') DEFAULT 'ordered',
  `delivery_note` text DEFAULT NULL,
  `buyer_confirmed` tinyint(1) DEFAULT 0,
  `seller_confirmed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_products` (
  `id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) DEFAULT 'General',
  `quantity` int(11) DEFAULT 1,
  `promo_tag` varchar(50) DEFAULT '',
  `views` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `boosted_until` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected','deletion_requested','paused') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_method` varchar(50) DEFAULT 'Pickup',
  `payment_agreement` varchar(50) DEFAULT 'Pay on delivery'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backup_users` (
  `id` int(11) NOT NULL DEFAULT 0,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('buyer','seller','admin') DEFAULT 'buyer',
  `seller_tier` enum('basic','pro','premium') DEFAULT 'basic',
  `department` varchar(100) DEFAULT NULL,
  `level` varchar(10) DEFAULT NULL,
  `hall` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `shop_banner` varchar(255) DEFAULT NULL,
  `whatsapp` varchar(100) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `linkedin` varchar(100) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `referral_code` varchar(50) DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `vacation_mode` tinyint(1) DEFAULT 0,
  `verified` tinyint(1) DEFAULT 0,
  `last_seen` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `faculty` varchar(120) DEFAULT NULL,
  `hall_residence` varchar(255) DEFAULT NULL,
  `last_upload_at` datetime DEFAULT NULL,
  `terms_accepted` tinyint(1) DEFAULT 0,
  `accepted_at` datetime DEFAULT NULL,
  `suspended` tinyint(1) DEFAULT 0,
  `tier_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discount_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `original_price` decimal(10,2) NOT NULL,
  `discount_percent` int(11) NOT NULL,
  `discounted_price` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `seller_id` (`seller_id`),
  CONSTRAINT `discount_requests_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discount_requests_ibfk_2` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `disputes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `complainant_id` int(11) NOT NULL,
  `target_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('open','resolved','closed') DEFAULT 'open',
  `admin_note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `admin_deleted` tinyint(1) DEFAULT 0,
  `delivery_status` enum('sent','delivered','seen') DEFAULT 'sent',
  `attachment_url` varchar(500) DEFAULT NULL,
  `message_type` enum('text','image','video','audio') DEFAULT 'text',
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_convo` (`sender_id`,`receiver_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('ordered','seller_seen','delivered','completed','disputed') DEFAULT 'ordered',
  `delivery_note` text DEFAULT NULL,
  `buyer_confirmed` tinyint(1) DEFAULT 0,
  `seller_confirmed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_buyer` (`buyer_id`),
  KEY `idx_seller` (`seller_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) DEFAULT 'General',
  `quantity` int(11) DEFAULT 1,
  `promo_tag` varchar(50) DEFAULT '',
  `views` int(11) DEFAULT 0,
  `clicks` int(11) DEFAULT 0,
  `boosted_until` datetime DEFAULT NULL,
  `status` enum('pending','approved','rejected','deletion_requested','paused') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_method` varchar(50) DEFAULT 'Pickup',
  `payment_agreement` varchar(50) DEFAULT 'Pay on delivery',
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_user` (`user_id`),
  KEY `idx_category` (`category`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `profile_edit_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `field_name` varchar(64) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `admin_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `profile_edit_requests` VALUES (1,9,'seller_tier','pro','basic','rejected',1,'2026-03-29 11:26:59','2026-03-29 11:27:30'),(2,9,'seller_tier','pro','basic','rejected',1,'2026-03-29 11:27:50','2026-03-29 11:51:47');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `referrals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `referrer_id` int(11) NOT NULL,
  `referred_user_id` int(11) NOT NULL,
  `bonus` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `referrer_id` (`referrer_id`),
  KEY `referred_user_id` (`referred_user_id`),
  CONSTRAINT `referrals_ibfk_1` FOREIGN KEY (`referrer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `referrals_ibfk_2` FOREIGN KEY (`referred_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_review` (`product_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `settings` VALUES ('ad_boost_price','11','2026-03-28 17:53:26'),('badge_color_basic','#0f59a3','2026-03-28 20:52:00'),('badge_color_premium','#ff9f0a','2026-03-28 19:13:16'),('badge_color_pro','#8e8e93','2026-03-28 19:13:16'),('basic_fee','0','2026-03-28 19:13:16'),('basic_image_limit','4','2026-03-28 20:52:00'),('basic_product_limit','6','2026-03-28 20:52:00'),('premium_batch_days','11','2026-03-28 20:52:00'),('premium_fee','16','2026-03-28 20:52:00'),('premium_image_limit','7','2026-03-28 20:52:00'),('premium_price','20','2026-03-28 17:53:31'),('premium_product_limit','24','2026-03-28 20:52:00'),('pro_batch_days','14','2026-03-28 19:13:16'),('pro_fee','14','2026-03-28 20:52:00'),('pro_image_limit','5','2026-03-28 20:52:00'),('pro_product_limit','12','2026-03-28 20:52:00');
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('deposit','purchase','sale','referral','withdrawal','boost','premium') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'completed',
  `reference` varchar(255) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `reference` (`reference`),
  KEY `idx_user_tx` (`user_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('buyer','seller','admin') DEFAULT 'buyer',
  `seller_tier` enum('basic','pro','premium') DEFAULT 'basic',
  `department` varchar(100) DEFAULT NULL,
  `level` varchar(10) DEFAULT NULL,
  `hall` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `shop_banner` varchar(255) DEFAULT NULL,
  `whatsapp` varchar(100) DEFAULT NULL,
  `instagram` varchar(100) DEFAULT NULL,
  `linkedin` varchar(100) DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `referral_code` varchar(50) DEFAULT NULL,
  `referred_by` int(11) DEFAULT NULL,
  `vacation_mode` tinyint(1) DEFAULT 0,
  `verified` tinyint(1) DEFAULT 0,
  `last_seen` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `faculty` varchar(120) DEFAULT NULL,
  `hall_residence` varchar(255) DEFAULT NULL,
  `last_upload_at` datetime DEFAULT NULL,
  `terms_accepted` tinyint(1) DEFAULT 0,
  `accepted_at` datetime DEFAULT NULL,
  `suspended` tinyint(1) DEFAULT 0,
  `tier_expires_at` datetime DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username_2` (`username`),
  UNIQUE KEY `email_2` (`email`),
  UNIQUE KEY `username_3` (`username`),
  UNIQUE KEY `email_3` (`email`),
  UNIQUE KEY `username_4` (`username`),
  UNIQUE KEY `email_4` (`email`),
  UNIQUE KEY `username_5` (`username`),
  UNIQUE KEY `email_5` (`email`),
  UNIQUE KEY `username_6` (`username`),
  UNIQUE KEY `email_6` (`email`),
  UNIQUE KEY `username_7` (`username`),
  UNIQUE KEY `email_7` (`email`),
  UNIQUE KEY `username_8` (`username`),
  UNIQUE KEY `email_8` (`email`),
  UNIQUE KEY `username_9` (`username`),
  UNIQUE KEY `email_9` (`email`),
  UNIQUE KEY `username_10` (`username`),
  UNIQUE KEY `email_10` (`email`),
  UNIQUE KEY `username_11` (`username`),
  UNIQUE KEY `email_11` (`email`),
  UNIQUE KEY `username_12` (`username`),
  UNIQUE KEY `email_12` (`email`),
  UNIQUE KEY `username_13` (`username`),
  UNIQUE KEY `email_13` (`email`),
  UNIQUE KEY `username_14` (`username`),
  UNIQUE KEY `email_14` (`email`),
  UNIQUE KEY `username_15` (`username`),
  UNIQUE KEY `email_15` (`email`),
  UNIQUE KEY `username_16` (`username`),
  UNIQUE KEY `email_16` (`email`),
  UNIQUE KEY `username_17` (`username`),
  UNIQUE KEY `email_17` (`email`),
  UNIQUE KEY `username_18` (`username`),
  UNIQUE KEY `email_18` (`email`),
  UNIQUE KEY `username_19` (`username`),
  UNIQUE KEY `email_19` (`email`),
  UNIQUE KEY `username_20` (`username`),
  UNIQUE KEY `email_20` (`email`),
  UNIQUE KEY `username_21` (`username`),
  UNIQUE KEY `email_21` (`email`),
  UNIQUE KEY `username_22` (`username`),
  UNIQUE KEY `email_22` (`email`),
  UNIQUE KEY `username_23` (`username`),
  UNIQUE KEY `email_23` (`email`),
  UNIQUE KEY `username_24` (`username`),
  UNIQUE KEY `email_24` (`email`),
  UNIQUE KEY `username_25` (`username`),
  UNIQUE KEY `email_25` (`email`),
  UNIQUE KEY `username_26` (`username`),
  UNIQUE KEY `email_26` (`email`),
  UNIQUE KEY `username_27` (`username`),
  UNIQUE KEY `email_27` (`email`),
  UNIQUE KEY `username_28` (`username`),
  UNIQUE KEY `email_28` (`email`),
  UNIQUE KEY `username_29` (`username`),
  UNIQUE KEY `email_29` (`email`),
  UNIQUE KEY `username_30` (`username`),
  UNIQUE KEY `referral_code` (`referral_code`),
  KEY `idx_role` (`role`),
  KEY `idx_referral` (`referral_code`),
  KEY `referred_by` (`referred_by`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`referred_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `users` VALUES (1,'admin','admin@campus.com','$2y$10$vWrze.56BXuCLe.uhdPOzu3evMvxjBWXInDTm1ZQpsLa7ruNxIV6i','admin','basic',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,100.00,'ADMIN001',NULL,0,1,'2026-03-29 18:10:58','2026-03-24 22:15:56',NULL,NULL,NULL,1,'2026-03-28 19:38:13',0,NULL,0,NULL);
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vacation_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seller_id` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_seller` (`seller_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
INSERT INTO `vacation_requests` VALUES (1,9,'rejected','2026-03-28 19:46:07'),(2,9,'rejected','2026-03-29 11:27:54'),(3,9,'rejected','2026-03-29 11:30:43'),(4,9,'rejected','2026-03-29 11:33:05'),(5,9,'rejected','2026-03-29 11:33:23'),(6,9,'rejected','2026-03-29 11:34:10'),(7,9,'rejected','2026-03-29 11:35:00'),(8,9,'pending','2026-03-29 11:53:01'),(9,9,'pending','2026-03-29 11:59:09');
