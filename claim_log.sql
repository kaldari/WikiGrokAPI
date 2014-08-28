--
-- Database: `wikigrok`
--

-- --------------------------------------------------------

--
-- Table structure for table `claim_log`
--

CREATE TABLE IF NOT EXISTS `claim_log` (
  `subject_id` varchar(12) NOT NULL,
  `subject` varchar(128) DEFAULT NULL,
  `claim_property_id` varchar(12) NOT NULL,
  `claim_property` varchar(128) DEFAULT NULL,
  `claim_value_id` varchar(12) NOT NULL,
  `claim_value` varchar(128) DEFAULT NULL,
  `page_name` varchar(255) DEFAULT NULL,
  `correct` tinyint(1) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `subject_id` (`subject_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
