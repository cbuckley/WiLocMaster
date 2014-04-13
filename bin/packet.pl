#!/usr/bin/perl
use DBI;
use Net::Stomp;
use Data::Dumper;
use JSON;
use strict;
$| = 1;

my $dbh = DBI->connect("DBI:mysql:wiloc:localhost", 'wiloc', 'q9MF2Jbed9S7DPmP') or die "Can't connect to db";
my $json = JSON->new->allow_nonref;
my $stomp = Net::Stomp->new( { hostname => 'localhost', port => '61613' } );
my $connection = $stomp->connect( { login => 'admin', passcode => 'password' } );

die("STOMP Connection Failed") if $connection->command eq "ERROR";

$stomp->subscribe(
	{
		destination             => '/queue/packets',
		'ack'                   => 'client',
        	'activemq.prefetchSize' => 1
      	}
);
#print "    BSSID   	" . "   SOURCE   	"."DESTINATION	". "    SSI    "."\n";
#print "------------	------------	------------	------------\n";

my $tickers;
while (1) {
	my $frame = $stomp->receive_frame;
	my $packet = $json->decode($frame->body );
	if(!$packet->{IEEE80211_FROM_DS} && $packet->{IEEE80211_TO_DS})	{
		print $packet->{IEEE80211_ADDR1_HEX} . "	" . $packet->{IEEE80211_ADDR2_HEX}."	".$packet->{IEEE80211_ADDR3_HEX}."	    ".$packet->{IEEE80211_RADIOTAP_SSI}."    \n";
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
				$tickers->{$packet->{IEEE80211_ADDR2_HEX}} = 0 if !defined $tickers->{$packet->{IEEE80211_ADDR2_HEX}};
				if($tickers->{$packet->{IEEE80211_ADDR2_HEX}} > 4)      {
                                	$tickers->{$packet->{IEEE80211_ADDR2_HEX}} = 0;
                                        generateSummary($packet->{IEEE80211_ADDR2_HEX});
                                }
				else	{
					$tickers->{$packet->{IEEE80211_ADDR2_HEX}}++;
				}
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
		$stomp->send({ destination => '/queue/web.ticks',body => $jsonstr });
	}
	$stomp->ack( { frame => $frame } );
}


sub generateSummary	{
	my ($mac) = @_;
	my $sql = "SELECT * FROM ticks WHERE clMac = ? and tseen BETWEEN NOW() - INTERVAL 10 second AND NOW() GROUP BY coId";
	my $sth = $dbh->prepare($sql);
	$sth->execute($mac);
	my $res = $sth->fetchall_hashref('tId');
	my %summaries;
	foreach my $key (keys $res)	{
		print Dumper($res->{$key});
	}
}
