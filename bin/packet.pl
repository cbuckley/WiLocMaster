#!/usr/bin/perl
use DBI;
use Net::Stomp;
use Data::Dumper;
use JSON;
use Switch;
use strict;
$| = 1;

my $dbh = DBI->connect("DBI:mysql:wiloc:localhost", 'wiloc', 'q9MF2Jbed9S7DPmP') or die "Can't connect to db";
my $json = JSON->new->allow_nonref;
my $stomp = Net::Stomp->new( { hostname => 'localhost', port => '61613' } );
my $connection = $stomp->connect( { login => 'admin', passcode => 'password' } );

die("STOMP Connection Failed") if $connection->command eq "ERROR";

$stomp->subscribe(
	{
		destination             => '/topic/packets',
		'ack'                   => 'client',
        	'activemq.prefetchSize' => 1
      	}
);
#print "    CNTRL   	" . "   SOURCE   	"."DESTINATION	". "    SSI    "\n";
#print "------------	------------	------------	------------\n";

my $tickers;
while (1) {
	my $frame = $stomp->receive_frame;
	my $packet = $json->decode($frame->body );
	if(!$packet->{IEEE80211_FROM_DS} && $packet->{IEEE80211_TO_DS})	{
		my $sql = "select * from clients where clMac = ?";
		my $sth = $dbh->prepare($sql);
		$sth->execute($packet->{IEEE80211_ADDR2_HEX});
		my $tick;
		if($sth->rows())	{
			$tick = $sth->fetchrow_hashref();
			$tick->{clSSI} = $packet->{IEEE80211_RADIOTAP_SSI};
			$tick->{coId} = $packet->{CONTROLLER_ID};
			if($tick->{clActive})	{
				$sql = "INSERT INTO `wiloc`.`ticks` (`clMac`, `tSSI`, `coId`, `tSeen`) VALUES (?, ?, ?, NOW())";
				$sth = $dbh->prepare($sql);
				$sth->execute($packet->{IEEE80211_ADDR2_HEX}, $packet->{IEEE80211_RADIOTAP_SSI}, $packet->{CONTROLLER_ID});
				$sql = "SELECT LAST_INSERT_ID()";
				$sth = $dbh->prepare($sql);
				$sth->execute();
				($packet->{tId}) = $sth->fetchrow_array();
				$tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$packet->{CONTROLLER_ID}}{time} = time();
				$tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$packet->{CONTROLLER_ID}}{SSI} = $packet->{IEEE80211_RADIOTAP_SSI};
				$tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$packet->{CONTROLLER_ID}}{tId} = $packet->{tId};
#				$tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$packet->{CONTROLLER_ID}}{ticks} = $packet;
				my $tbreak = 1;
				my $keys = scalar keys $tickers->{$packet->{IEEE80211_ADDR2_HEX}};
				$tbreak = 0 if !($keys == 3);
				foreach my $tkey (keys $tickers->{$packet->{IEEE80211_ADDR2_HEX}})	{
					if ($tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$tkey}{time} < time - 10)	{
						$tbreak = 0;
						delete $tickers->{$packet->{IEEE80211_ADDR2_HEX}}{$tkey};
					}
					
				}	
				generateSummary($tickers->{$packet->{IEEE80211_ADDR2_HEX}}, $packet->{IEEE80211_ADDR2_HEX}) if $tbreak;
				print $packet->{CONTROLLER_ID} . "      " . $packet->{IEEE80211_ADDR2_HEX}."    ".$packet->{IEEE80211_ADDR3_HEX}."          ".$packet->{IEEE80211_RADIOTAP_SSI}."       ".$keys."\n";
			}
		}
		else	{
			#Not currently tracking
			$tick->{clMac} = $packet->{IEEE80211_ADDR2_HEX};
			$tick->{clActive} = 0;
			$tick->{clSSI} = $packet->{IEEE80211_RADIOTAP_SSI};
			$tick->{coId} = $packet->{CONTROLLER_ID};
		}
		my $jsonstr = $json->encode($tick);
		$stomp->send({ destination => '/topic/web.ticks',body => $jsonstr });
	}
	$stomp->ack( { frame => $frame } );
}


sub generateSummary	{
	my ($mac, $macAddr) = @_;
	my ($sql, $sth, $result);
	print "Begin Trilateration\n";
	foreach my $key (keys $mac)	{
		$sql = "SELECT * FROM controllerLocs where coId = ? ORDER BY clTimestamp DESC LIMIT 1";
		$sth = $dbh->prepare($sql);
		$sth->execute($key);
		$result = $sth->fetchrow_hashref();	
		$mac->{$key}{lat} =  $result->{clLat};
		$mac->{$key}{lon} =  $result->{clLon};
		$mac->{$key}{distance} = 7 if $mac->{$key}{SSI} > -55;
		$mac->{$key}{distance} = 6 if $mac->{$key}{SSI} < -55;
		$mac->{$key}{distance} = 5 if $mac->{$key}{SSI} < -60;
		$mac->{$key}{distance} = 4 if $mac->{$key}{SSI} < -65;
		$mac->{$key}{distance} = 3 if $mac->{$key}{SSI} < -70;
		$mac->{$key}{distance} = 2 if $mac->{$key}{SSI} < -80;
		$mac->{$key}{distance} = 1 if $mac->{$key}{SSI} < -90;
		$mac->{$key}{distance} *= 3;
	}
	my @keys = keys $mac;
	my $S = ($mac->{$keys[2]}{lat} ** 2 - $mac->{$keys[1]}{lat} ** 2 + $mac->{$keys[2]}{lon} ** 2 - $mac->{$keys[1]}{lon} ** 2 + $mac->{$keys[1]}{distance} ** 2 - $mac->{$keys[2]}{distance} ** 2) / 2.0;
	my $T = ($mac->{$keys[0]}{lat} ** 2 - $mac->{$keys[1]}{lat} ** 2 + $mac->{$keys[0]}{lon} ** 2 - $mac->{$keys[1]}{lon} ** 2 + $mac->{$keys[1]}{distance} **2 - $mac->{$keys[0]}{distance} **2) / 2.0;
	my $y = (($T * ($mac->{$keys[1]}{lat} - $mac->{$keys[2]}{lat})) - ($S * ($mac->{$keys[1]}{lat} - $mac->{$keys[0]}{lat}))) / ((($mac->{$keys[0]}{lon} - $mac->{$keys[1]}{lon}) * ($mac->{$keys[1]}{lat} - $mac->{$keys[2]}{lat})) - (($mac->{$keys[2]}{lon} - $mac->{$keys[1]}{lon}) * ($mac->{$keys[1]}{lat} - $mac->{$keys[0]}{lat})));
	my $x = (($y * ($mac->{$keys[0]}{lon} - $mac->{$keys[1]}{lon})) - $T) / ($mac->{$keys[1]}{lat} - $mac->{$keys[0]}{lat});
	$sql = "INSERT INTO `wiloc`.`clientSummary` (`clMac`, `csGenerated`, `csLat`, `csLon`) VALUES (?, NOW(), ?, ?)";
	$sth = $dbh->prepare($sql);
	$sth->execute($macAddr, $x, $y);
	$sql = "SELECT LAST_INSERT_ID()";
	$sth = $dbh->prepare($sql);
	$sth->execute();
	my ($csid) = $sth->fetchrow_array();
	foreach my $key (keys $mac)     {
		$sql = "INSERT INTO `wiloc`.`clientTicks` (`csId`, `tId`) VALUES (?, ?)";
		$sth = $dbh->prepare($sql);
		$sth->execute($csid, $mac->{$key}{tId});
		$mac->{$key}{lat} = $result->{clLat};
                $mac->{$key}{lon} = $result->{clLon};
	}
	print "Location: $x, $y\n";
	my $summary;
	$summary->{clMac} = $macAddr;
	$summary->{clLat} = $x;
	$summary->{clLon} = $y;
	$summary->{controllers} = $mac;
	my $jsonstr = $json->encode($summary);
        $stomp->send({ destination => '/topic/web.summary',body => $jsonstr });
}
