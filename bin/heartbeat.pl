#!/usr/bin/perl
use DBI;
use Net::Stomp;
use Data::Dumper;
use JSON;
use strict;
$| = 1;
#Connect to DB and message queue
my $dbh = DBI->connect("DBI:mysql:wiloc:localhost", 'wiloc', 'q9MF2Jbed9S7DPmP') or die "Can't connect to db";
my $json = JSON->new->allow_nonref;
my $stomp = Net::Stomp->new( { hostname => 'localhost', port => '61613' } );
my $connection = $stomp->connect( { login => 'admin', passcode => 'password' } );

die("STOMP Connection Failed") if $connection->command eq "ERROR";

$stomp->subscribe(
	{
		destination             => '/topic/heartbeats',
		'ack'                   => 'client',
        	'activemq.prefetchSize' => 1
      	}
);

my $controllers = updateControllers(); #Update the controller list

while (1) {
	my $frame = $stomp->receive_frame;
	my $heartbeat = $json->decode($frame->body );
	$controllers = updateControllers();
	if($controllers->{$heartbeat->{controller}})	{ #Is the controller already in the system
		print "Controller found\n";
		my $sql = "UPDATE controllers SET coSeen = NOW() WHERE coId = ?";
                my $sth = $dbh->prepare($sql);
                $sth->execute($heartbeat->{controller}); #Update the controller seen time in the DB
		$controllers->{$heartbeat->{controller}}{coSeen} = time(); #New seen time for perls reference
	}
	else	{
		print "Controller is not found, setting up...\n";
		my $sql = "INSERT INTO controllers (`coId`, `coSeen`, `coActive`) VALUES (?,NOW(), ?)";
		my $sth = $dbh->prepare($sql);
		$sth->execute($heartbeat->{controller}, 0); # Set up new controller in the DB
	}
	my $jsonstr = $json->encode($controllers->{$heartbeat->{controller}}); # Convert to JSON
	$stomp->send({ destination => '/topic/web.heartbeats',body => $jsonstr }); # Send the message queue heartbeat to web
	$stomp->ack( { frame => $frame } );
}

sub updateControllers	{
	my $sql = "select *, unix_timestamp(coSeen) as coSeen from controllers";
	my $sth = $dbh->prepare($sql);
	$sth->execute();
	return $sth->fetchall_hashref('coId'); # Populate hashref with new database objects
}
