INSERT INTO users_permissions (permName, permDesc, level, type) VALUES ('tiki_p_list_newsletters', 'Can list newsletters', 'basic', 'newsletters');
INSERT INTO `tiki_menu_options` (`optionId`, `menuId`, `type`, `name`, `url`, `position`, `section`, `perm`, `groupname`, `userlevel`) VALUES (107,42,'s','Newsletters','tiki-newsletters.php',900,'feature_newsletters','tiki_p_list_newsletters','',0);
INSERT INTO users_grouppermissions (groupName,permName) VALUES('Anonymous','tiki_p_list_newsletters');