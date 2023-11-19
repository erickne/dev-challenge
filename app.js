// Define globals
const API_HOST = 'http://localhost/request.php'
let deleteItemId
let summaryTable
let loading

// Function to toggle the visibility of the edit form
function toggleEditForm (id) {

  $('#editFormContainer').show()
  $('#summaryContainer').hide()

  // Form Create
  if (!id) { return}

  // Form Edit - Fetch data for the given id and populate form
  $.ajax({
    url: `${API_HOST}?_route=requests&id=${id}`, method: 'GET', success: function (data) {
      // Populate the form inputs with the fetched data
      $('#editRequestedBy').val(data.data.requested_by)
      $('#editItemId').val(data.data.id)
    }, error: function () {
      console.error('Error fetching data for id: ' + id)
    }
  })
}

function cancelEditForm () {
  $('#editFormContainer').hide()
  $('#summaryContainer').show()
}

function deleteRequestModel () {

  $.ajax({
    url: `${API_HOST}?_route=requests&id=${deleteItemId}`, method: 'DELETE', success: function (response) {
      console.log('Item deleted successfully')

      // Close the confirmation modal
      $('#confirmationModal').modal('hide')

      // Reload the Summary DataTable
      summaryTable.ajax.reload()
    }, error: function (error) {
      console.error('Error deleting item:', error)
    }
  })
}

function showConfirmationModal (id) {

  deleteItemId = id

  $('#confirmationModal').modal('show')
}

function saveForm () {
  const formData = {
    requested_by: $('#editRequestedBy').val(),
  }

  // Check if there's an itemId
  const itemId = $('#editItemId').val()

  let url = `${API_HOST}?_route=requests`
  let method = 'POST'

  // If itemId exists, it's an edit operation (PUT)
  if (itemId) {
    formData.id = itemId
    method = 'PUT'
  }
  loading.show()

  // Make an AJAX request to save
  $.ajax({
    url: url,
    method: method,
    contentType: 'application/json',
    data: JSON.stringify(formData),
    success: function (response) {
      console.log('Changes saved successfully')

      summaryTable.ajax.reload()

      $('#editFormContainer').hide()
      $('#summaryContainer').show()
      loading.hide()

    },
    error: function (error) {
      console.error('Error saving changes:', error)
    }
  })
}

$(document).ready(function () {

  loading = $('#loading-overlay')
  loading.show()

  summaryTable = $('#summaryTable').DataTable({
    paging: false, initComplete: function (settings, json) {
      loading.hide()
    }, ajax: {
      url: `${API_HOST}?_route=requests`, dataSrc: 'data'
    },
    processing: true,
    columns: [{
      data: 'id', render: function (data) {
        return `
            <div class="btn-group">
              <button type="button" class="btn btn-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-cog"></i>
              </button>
              <div class="dropdown-menu">
                <a class="dropdown-item" href="#" onclick="toggleEditForm(${data})"><i class="fas fa-edit"></i> Edit</a>
                <a class="dropdown-item" href="#" onclick="showConfirmationModal(${data})"><i class="fas fa-trash-alt"></i> Delete</a>

              </div>
            </div>
          `
      }
    },
      { data: 'requested_by' },
      { data: 'requested_on' },
      { data: 'requested_on' },
      { data: 'requested_on' },
      { data: 'requested_on' },
      { data: 'requested_on' },
    ],

  })

  $('#summaryContainer').show()
})