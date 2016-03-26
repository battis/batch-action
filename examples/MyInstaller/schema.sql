CREATE TABLE `test1` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `data` int(11) DEFAULT NULL,
  `data2` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
CREATE TABLE `test2` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `more_data` text,
  `lots o data` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;