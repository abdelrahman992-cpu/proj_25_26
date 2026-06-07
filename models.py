from sqlalchemy import Column, Integer, String, Enum, ForeignKey, Text, DateTime, TIMESTAMP, Table
from sqlalchemy.orm import relationship
from database import Base
import enum
import datetime 
from sqlalchemy.sql import func



class UserRole(str, enum.Enum):
    admin = "admin"
    user = "user"

class User(Base):
    __tablename__ = "users"
    id = Column(Integer, primary_key=True, index=True)
    username = Column(String(30), nullable=False)
    passwor = Column(String(255))
    role = Column(Enum(UserRole), default=UserRole.user)
    email = Column(String(255))
    phone = Column(String(20))
    login_attempts = Column(Integer, default=0)
    last_attempt_time = Column(DateTime)
    pending_name = Column(String(255), nullable=True)
    pending_email = Column(String(255), nullable=True)
    pending_phone = Column(String(255), nullable=True)
    upgrade_requests = relationship("UpgradeRequest", back_populates="user_obj")

class Section(Base):
    __tablename__ = "sections"
    s_id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, nullable=False)
    username = Column(String(50), nullable=False)
    deta = Column(Text)
    last_activity = Column(TIMESTAMP)

class Term(Base):
    __tablename__ = "terms"
    id = Column(Integer, primary_key=True, index=True)
    term = Column(String(50), nullable=False)
    trans = Column(Text)
    defe = Column(Text)
    smiles_code = Column(Text)
    picture = Column(String(255), default='yyy.jpg')
    user_id = Column(Integer)
    status = Column(String(20), default='pending')
class OTP(Base):
    __tablename__ = "otp_codes"
    
    id = Column(Integer, primary_key=True)
    user_id = Column(Integer)
    code = Column(String(6))           # للتحقق العام
    expires_at = Column(DateTime)
    delete_otp = Column(String(6))
    delete_expire = Column(DateTime)
    reset_code = Column(String(6))     # كود استعادة كلمة المرور
    reset_expire = Column(DateTime)
    edit_code=Column(String(6)) 
    edit_expire = Column(DateTime)

   

# يمكنك إضافة كلاسات أخرى مثل UpgradeRequest و FailedAttempts بنفس الطريقة

class FailedAttempt(Base):
    __tablename__ = "failed_attempts"
    id = Column(Integer, primary_key=True, index=True)
    ip_address = Column(String(45), nullable=False)
    attempt_time = Column(TIMESTAMP, server_default=func.now())

class UpgradeRequest(Base):
    __tablename__ = "upgrade_requests"
    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id", ondelete="CASCADE"))

    user_obj = relationship("User", back_populates="upgrade_requests")


