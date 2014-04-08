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
		destination             => '/queue/heartbeats',
		'ack'                   => 'client',
        	'activemq.prefetchSize' => 1
      	}
);

my $controllers = updateControllers();

while (1) {
	my $frame = $stomp->receive_frame;
	my $heartbeat = $json->decode($frame->body );
	if($controllers->{$heartbeat->{controller}})	{
		print "Controller found\n";
		my $sql = "UPDATE controllers SET coSeen = NOW() WHERE coId = ?";
                my $sth = $dbh->prepare($sql);
                $sth->execute($heartbeat->{controller});
		$controllers->{$heartbeat->{controller}}{coSeen} = time();
	}
	else	{
		print "Controller is not found, setting up...\n";
		my $sql = "INSERT INTO controllers (`coId`, `coSeen`, `coActive`) VALUES (?,NOW(), ?)";
		my $sth = $dbh->prepare($sql);
		$sth->execute($heartbeat->{controller}, 0);
		$controllers = updateControllers();
	}
	my $jsonstr = $json->encode($controllers->{$heartbeat->{controller}});
	$stomp->send({ destination => '/queue/web.heartbeats',body => $jsonstr });
	$stomp->ack( { frame => $frame } );
}

sub updateControllers	{
	my $sql = "select *, unix_timestamp(coSeen) as coSeen from controllers";
	my $sth = $dbh->prepare($sql);
	$sth->execute();
	return $sth->fetchall_hashref('coId');
}
