package com.example.ttnadmin.order.create_new_order_activity.event.hotel.viewRoom.add_on.order_review

import android.annotation.SuppressLint
import androidx.appcompat.app.AppCompatActivity
import android.os.Bundle
import android.provider.Settings
import android.util.Log
import android.view.View
import android.widget.Toast
import androidx.lifecycle.lifecycleScope
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import com.example.ttnadmin.R
import com.example.ttnadmin.retrofit.Api
import com.example.ttnadmin.retrofit.RetrofitClient
import com.example.ttnadmin.retrofit.StoreDataPreference
import com.example.ttnadmin.retrofit.api_response.order_review.Data
import com.example.ttnadmin.retrofit.api_response.order_review.OrderReviewResponse
import kotlinx.android.synthetic.main.activity_add_ons_detail.*
import kotlinx.android.synthetic.main.activity_event_add_ons.*
import retrofit2.Call
import retrofit2.Callback
import retrofit2.Response

class AddOnsDetailActivity : AppCompatActivity(),View.OnClickListener {
    private var addOnsAdapter: EventAddOnsDetailsAdapter?= null
    private val addOnsList = ArrayList<Data>()
    private var getToken=""
    private var getItemId=""
    private var getTempId=""
    private var deviceId=""
    private var type=2
    private var deviceType=2
    private lateinit var storeDataPreference: StoreDataPreference
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_add_ons_detail)
        addOnsCrossIcon.setOnClickListener(this)
        storeDataPreference = StoreDataPreference(this)

        getTempId=intent.getStringExtra("send_temp_id").toString()
        getItemId=intent.getStringExtra("send_item_id").toString()


        // get device ID

        deviceId = Settings.Secure.getString(contentResolver, Settings.Secure.ANDROID_ID)

        val eventCategoryManager = LinearLayoutManager(this, RecyclerView.VERTICAL, false)
        addOnsRecyclerView.layoutManager = eventCategoryManager

        addOnsAdapter = EventAddOnsDetailsAdapter(this,addOnsList)

        lifecycleScope.launchWhenCreated {
            getToken ="Bearer "+storeDataPreference.getToken(StoreDataPreference.TOKEN).toString()
            getAddOnsList(getToken,getItemId,getTempId,deviceId,deviceType,type)

        }

    }

    // on click implementation

    override fun onClick(v: View) {
        when(v.id)
        {
            R.id.addOnsCrossIcon ->{
                finish()
            }
        }

    }

    // Add On list Api implementation

    @SuppressLint("SuspiciousIndentation")
    private fun getAddOnsList(token:String, itemId:String, tempId:String, deviceId:String, deviceType:Int, itemType:Int)
    {
        val retro = RetrofitClient.getRetroClientInstance()?.create(Api::class.java)
          retro?.adminOrderReview(token,itemId,tempId,deviceId,deviceType,itemType)?.enqueue(object:Callback<OrderReviewResponse>{
              override fun onResponse(
                  call: Call<OrderReviewResponse>,
                  response: Response<OrderReviewResponse>,
              ) {
                  if (response.isSuccessful)
                  {
                      if (response.body()?.status==1)
                      {
                          addOnsList.add(response.body()!!.data)
                          addOnsRecyclerView.adapter = addOnsAdapter
                          addOnsAdapter?.notifyDataSetChanged()
                          Toast.makeText(this@AddOnsDetailActivity,response.body()?.msg,Toast.LENGTH_SHORT).show()
                          Log.d("response",""+response)


                      }else{
                          Toast.makeText(this@AddOnsDetailActivity,response.body()?.msg,Toast.LENGTH_SHORT).show()
                          Log.d("failure",""+response)

                      }
                  }

              }

              override fun onFailure(call: Call<OrderReviewResponse>, t: Throwable) {
                  Toast.makeText(this@AddOnsDetailActivity,t.message,Toast.LENGTH_SHORT).show()
                  Log.d("onFailure",""+t.message)
              }

          })
    }
}