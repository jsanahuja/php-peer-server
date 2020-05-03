# PHPPeerServer
PHP Socket.io server for WebRTC signaling.

Client: https://github.com/jsanahuja/peer-client

Demo: http://webrtc.sowecms.com/client/index.html

## Server events

The following is the list of events you can send to the server. For example `socket.emit('join', 'a68ca609389b6ba7f0766b9ed1bfd8ca')`

> `create(name, password)` creates a room.

> `join(roomId, password)` joins a room.

> `leave()` leaves the current room.

> `kick(userId)` kicks an user from the room. Can only be used by the creator.

> `ban(userId)` bans an user from the room. Can only be used by the creator.

> `unban(userId)` unbans an user from the room. Can only be used by the creator.

> `message(msg)` sends a message to the current room.

> `toggle(resource)` toggles a resource. The resources are 'screen', 'video' and 'audio'.

> `candidate(callId, candidate)` sends a candidate.

> `offer(callId, offer)` sends an offer. Must have been requested with the `call` event.

> `answer(callId, answer)` sends an answer. Must have been requested with the `offer` event.

## Server responses

The following are the events the server will trigger.

> `created(roomId)` confirms you created a room.

> `joined(roomId)` confirms you joined the room.

> `left(roomId)` confirms you left the room.

> `kicked` notifies that you have been kicked from the room.

> `banned` notifies that you have been banned from the room.

> `unbanned` notifies that you have been unbanned from a room.

> `call(callId)` requests an offer for the call

> `offer(callId, offer)` sends and offer and requires an answer

> `answer(callId, answer)` sends an answer to the offer

> `hangup(callId)` notifies that the call was closed

Also the error events:

> `join_alreadyin` notifies you can't join the room because you're already in.

> `join_wrongpass` notifies you can't join the room because the password provided is wrong.

> `join_full` notifies you can't join the room because it is full.

> `join_banned` notifies you can't join the room because you are banned.

> `kick_noprivileges` notifies you don't have enough privileges to kick in the current room.

> `kick_notin` notifies that the user you tried to kick is no longer in the room.

> `ban_noprivileges` notifies you don't have enough privileges to ban in the current room.

> `ban_already` notifies that the user you tried to ban is already banned.

> `unban_noprivileges` notifies you don't have enough privileges to unban in the current room.

> `unban_notbanned` notifies you have tried to unban an user that wasn't banned.

And the room events:

> `r_message(userId, message)` someone in your room sent a text message in your room.

> `r_resource(userId, resource, status)` someone in your room toggled the status of one of its resources.

> `r_joined(userId)` an user joined your room.

> `r_left(userId)` an user left your room.

> `r_kicked(userId)` an user was kicked out of your room.

> `r_banned(userId)` an user from your room was banned.

> `r_unbanned(userId)` an user has been unbanned.
