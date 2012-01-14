$(document).ready ->
	log = (msg) -> $('#log').append("#{msg}<br />")	
	serverUrl = 'ws://localhost:8000/demo'
	if $.browser.mozilla
		socket = new MozWebSocket(serverUrl)
	else
		socket = new WebSocket(serverUrl)
	socket.binaryType = 'blob'

	socket.onopen = (msg) ->
		$('#status').removeClass().addClass('online').html('connected')
	
	socket.onmessage = (msg) ->
		response = JSON.parse(msg.data)
		log("Action: #{response.action}")
		log("Data: #{response.data}")
	
	socket.onclose = (msg) ->
		$('#status').removeClass().addClass('offline').html('disconnected')
	
	$('#status').click ->
		socket.close()
	
	$('#send').click ->
		payload = new Object()
		payload.action = $('#action').val()
		payload.data = $('#data').val()
		socket.send(JSON.stringify(payload))
		
	$('#sendfile').click ->
		data = document.binaryFrame.file.files[0]
		if data			
			payload = new Object()
			payload.action = 'setFilename'
			payload.data = $('#file').val()			
			socket.send JSON.stringify payload
			i for i in [1..1000000]
			socket.send(data)
		return false
		