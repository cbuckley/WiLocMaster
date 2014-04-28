#!/usr/bin/perl
use Net::Stomp;
use Data::Dumper;
use JSON;
use strict;
$| = 1;
my $stomp = Net::Stomp->new( { hostname => '192.168.10.99', port => '61613' } );
$stomp->connect( { login => 'admin', passcode => 'password' } );

my @controllers = ("fnk8rjf43e","lrhcngawjz","Mp2H715b45");

my $clients;

$clients->{111111111111} = [-85,-63,-63];
$clients->{222222222222} = [-67,-69,-59];
$clients->{333333333333} = [-70,-64,-64];
$clients->{444444444444} = [-63,-64,-85];
$clients->{555555555555} = [-59,-79,-66];
$clients->{666666666666} = [-54,-64,-67];
my $json = JSON->new->allow_nonref;
while (1)	{
	foreach my $client (keys $clients)	{
		print "Client $client\n";
		for(my $y = 0; $y <=2; $y++)	{
			print "Controller " . $controllers[$y] . " has SSI " . $clients->{$client}[$y] . "\n";
			my $send;
			$send->{IEEE80211_FROM_DS} = 0;
			$send->{IEEE80211_TO_DS} = 1;
			$send->{IEEE80211_RADIOTAP_SSI} = $clients->{$client}[$y];
			$send->{CONTROLLER_ID} = $controllers[$y];
			$send->{IEEE80211_ADDR2_HEX} = $client;
			my $jsonstr = $json->encode($send);
		        $stomp->send({ destination => '/topic/packets', body => $jsonstr } );
		}
	}
	sleep(10);
}
